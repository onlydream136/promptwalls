<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileRecord;
use App\Models\ProcessLog;
use App\Models\SystemConfig;
use App\Models\WordPair;
use App\Services\FileConverterService;
use App\Services\ReIdentifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;

class ReIdentifyController extends Controller
{
    public function __construct(
        private ReIdentifierService $reIdentifier,
        private FileConverterService $converter,
    ) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        // Save uploaded file with correct extension (PhpWord needs .docx to open as zip)
        $tmpDir = storage_path('app/temp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $tmpName = uniqid('reident_') . '.' . $extension;
        $file->move($tmpDir, $tmpName);
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpName;

        try {
            if (in_array($extension, ['txt', 'csv', 'log', 'json'])) {
                $content = file_get_contents($tmpPath);
            } else {
                $content = $this->converter->convertToText($tmpPath);
            }
        } finally {
            @unlink($tmpPath);
        }

        // Auto-detect which file record's pairs match
        $fileRecordId = $this->reIdentifier->detectFileRecord($content, auth()->id());

        return response()->json([
            'content' => $content,
            'detected_file_id' => $fileRecordId,
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string',
            'file_record_id' => 'nullable|integer',
        ]);

        $text = $request->input('text');
        $fileRecordId = $request->input('file_record_id');

        $result = $this->reIdentifier->reidentify($text, auth()->id());

        // Log the re-identification action
        if ($fileRecordId) {
            ProcessLog::create([
                'file_record_id' => $fileRecordId,
                'action' => 'reidentify',
                'details' => "Re-identified {$result['replacements_made']} entities",
            ]);
        }

        return response()->json($result);
    }

    /**
     * Upload a desensitized file, restore placeholders, and return the file in its original format.
     */
    public function restore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'file_record_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $tmpDir = storage_path('app/temp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $tmpName = uniqid('restore_') . '.' . $extension;
        $file->move($tmpDir, $tmpName);
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpName;

        $fileRecordId = $request->input('file_record_id');

        // Get all word pairs for current user
        $pairs = WordPair::where('user_id', auth()->id())->get();
        $replacements = [];
        foreach ($pairs as $pair) {
            $replacements[$pair->placeholder] = $pair->original_value;
        }

        $outputPath = $tmpDir . DIRECTORY_SEPARATOR . 'restored_' . $originalName . '.' . $extension;

        try {
            switch ($extension) {
                case 'docx':
                case 'doc':
                    $phpWord = WordIOFactory::load($tmpPath);
                    foreach ($phpWord->getSections() as $section) {
                        $this->replaceInWordElements($section->getElements(), $replacements);
                    }
                    $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
                    $writer->save($outputPath);
                    break;

                case 'xlsx':
                case 'xls':
                    $spreadsheet = SpreadsheetIOFactory::load($tmpPath);
                    foreach ($spreadsheet->getAllSheets() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            foreach ($row->getCellIterator() as $cell) {
                                $val = (string) $cell->getValue();
                                if (!empty($val)) {
                                    $newVal = str_replace(array_keys($replacements), array_values($replacements), $val);
                                    if ($newVal !== $val) {
                                        $cell->setValue($newVal);
                                    }
                                }
                            }
                        }
                    }
                    $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->save($outputPath);
                    break;

                case 'csv':
                case 'txt':
                case 'log':
                case 'json':
                    $content = file_get_contents($tmpPath);
                    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                    file_put_contents($outputPath, $content);
                    break;

                default:
                    // Unsupported format, treat as text
                    $content = file_get_contents($tmpPath);
                    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                    $outputPath = $tmpDir . DIRECTORY_SEPARATOR . 'restored_' . $originalName . '.txt';
                    file_put_contents($outputPath, $content);
                    $extension = 'txt';
                    break;
            }

            $mimeTypes = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc' => 'application/msword',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xls' => 'application/vnd.ms-excel',
                'csv' => 'text/csv',
                'txt' => 'text/plain',
                'json' => 'application/json',
            ];

            $downloadName = 'restored_' . $originalName . '.' . $extension;

            // Save to restored directory
            $restoredDir = SystemConfig::get('restored_files_path', storage_path('files/RestoredFiles'));
            if (!is_dir($restoredDir)) {
                mkdir($restoredDir, 0755, true);
            }
            $savedPath = $restoredDir . DIRECTORY_SEPARATOR . $downloadName;
            copy($outputPath, $savedPath);

            // Create file record
            FileRecord::create([
                'filename' => $downloadName,
                'source_path' => $savedPath,
                'output_path' => $savedPath,
                'file_type' => $extension,
                'file_size' => filesize($outputPath),
                'status' => 'restored',
                'folder' => 'restored',
                'user_id' => auth()->id(),
            ]);

            return response()->download($outputPath, $downloadName, [
                'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            @unlink($tmpPath);
            @unlink($outputPath);
            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function replaceInWordElements($elements, array $replacements): void
    {
        foreach ($elements as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                $text = $element->getText();
                if ($text) {
                    $newText = str_replace(array_keys($replacements), array_values($replacements), $text);
                    if ($newText !== $text) {
                        $element->setText($newText);
                    }
                }
            } elseif (method_exists($element, 'getElements')) {
                $this->replaceInWordElements($element->getElements(), $replacements);
            }
        }
    }

    public function pairs(int $fileId): JsonResponse
    {
        $pairs = WordPair::where('file_record_id', $fileId)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'placeholder' => $p->placeholder,
                'original_value' => $p->original_value,
                'entity_type' => $p->entity_type,
            ]);

        $file = FileRecord::find($fileId);

        return response()->json([
            'file_id' => $fileId,
            'filename' => $file?->filename,
            'total_pairs' => $pairs->count(),
            'pairs' => $pairs,
        ]);
    }

    public function listFiles(): JsonResponse
    {
        // List files that have word pairs (for the re-identification tool)
        $files = FileRecord::whereHas('wordPairs')
            ->withCount('wordPairs')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'filename' => $f->filename,
                'word_pairs_count' => $f->word_pairs_count,
                'created_at' => $f->created_at->toDateTimeString(),
            ]);

        return response()->json($files);
    }
}
