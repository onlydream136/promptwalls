<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileRecord;
use App\Models\ProcessLog;
use App\Models\WordPair;
use App\Services\FileConverterService;
use App\Services\ReIdentifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $fileRecordId = $this->reIdentifier->detectFileRecord($content);

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

        $result = $this->reIdentifier->reidentify($text, $fileRecordId);

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
