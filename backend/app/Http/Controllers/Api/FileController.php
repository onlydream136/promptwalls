<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFileJob;
use App\Models\FileRecord;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    private function scopedQuery(Request $request)
    {
        $query = FileRecord::query();
        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }
        return $query;
    }

    public function counts(Request $request): JsonResponse
    {
        $counts = $this->scopedQuery($request)
            ->selectRaw("folder, COUNT(*) as count")
            ->groupBy('folder')
            ->pluck('count', 'folder')
            ->toArray();

        return response()->json([
            'incoming' => $counts['incoming'] ?? 0,
            'clean' => $counts['clean'] ?? 0,
            'sensitive' => $counts['sensitive'] ?? 0,
            'desensitized' => $counts['desensitized'] ?? 0,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $folder = $request->get('folder', 'incoming');
        $search = $request->get('search', '');
        $perPage = (int) $request->get('per_page', 20);

        $query = $this->scopedQuery($request);

        if ($folder !== 'all') {
            $query->where('folder', $folder);
        }

        if ($search) {
            $query->where('filename', 'like', "%{$search}%");
        }

        // Single query with paginate() to reduce remote DB round-trips
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn($f) => [
                'id' => $f->id,
                'filename' => $f->filename,
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'status' => $f->status,
                'folder' => $f->folder,
                'created_at' => $f->created_at->toDateTimeString(),
                'updated_at' => $f->updated_at->toDateTimeString(),
                'has_output' => !empty($f->output_path),
            ]),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:102400', // 100MB max
        ]);

        $incomePath = SystemConfig::get('income_files_path', 'C:\\IncomeFiles');

        if (!is_dir($incomePath)) {
            mkdir($incomePath, 0755, true);
        }

        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $filename = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileType = strtolower($file->getClientOriginalExtension());
            $destPath = $incomePath . DIRECTORY_SEPARATOR . $filename;

            // Avoid overwriting - add suffix if file exists
            $counter = 1;
            while (file_exists($destPath)) {
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $destPath = $incomePath . DIRECTORY_SEPARATOR . "{$name}_{$counter}.{$ext}";
                $counter++;
            }

            $file->move($incomePath, basename($destPath));

            $record = FileRecord::create([
                'filename' => $filename,
                'file_type' => $fileType,
                'file_size' => $fileSize ?: filesize($destPath),
                'status' => 'pending',
                'folder' => 'incoming',
                'source_path' => $destPath,
                'user_id' => $request->user()->id,
            ]);

            // Dispatch processing job
            ProcessFileJob::dispatch($record->id);

            $uploaded[] = [
                'id' => $record->id,
                'filename' => $record->filename,
                'status' => 'pending',
            ];
        }

        return response()->json([
            'message' => count($uploaded) . ' file(s) uploaded successfully',
            'files' => $uploaded,
        ]);
    }

    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        $file = FileRecord::findOrFail($id);

        // Prefer output path (desensitized version), fallback to source
        $path = $file->output_path ?: $file->source_path;

        if (!$path || !file_exists($path)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        // Use actual output filename so extension matches content
        $downloadName = $file->output_path ? basename($file->output_path) : $file->filename;

        return response()->download($path, $downloadName);
    }

    public function destroy(int $id): JsonResponse
    {
        $file = FileRecord::findOrFail($id);
        $file->delete();

        return response()->json(['message' => 'File record deleted']);
    }

    public function retry(int $id): JsonResponse
    {
        $file = FileRecord::findOrFail($id);

        if ($file->status !== 'failed') {
            return response()->json(['message' => 'Only failed files can be retried'], 422);
        }

        // Reset for retry
        $file->update([
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        ProcessFileJob::dispatch($file->id);

        return response()->json(['message' => 'File queued for reprocessing']);
    }

    public function show(int $id): JsonResponse
    {
        $file = FileRecord::with(['wordPairs', 'processLogs'])->findOrFail($id);

        return response()->json([
            'id' => $file->id,
            'filename' => $file->filename,
            'file_type' => $file->file_type,
            'file_size' => $file->file_size,
            'status' => $file->status,
            'folder' => $file->folder,
            'source_path' => $file->source_path,
            'output_path' => $file->output_path,
            'extracted_text' => $file->extracted_text,
            'assessment_result' => $file->assessment_result,
            'word_pairs' => $file->wordPairs->map(fn($p) => [
                'placeholder' => $p->placeholder,
                'original_value' => $p->original_value,
                'entity_type' => $p->entity_type,
            ]),
            'process_logs' => $file->processLogs->map(fn($l) => [
                'action' => $l->action,
                'details' => $l->details,
                'created_at' => $l->created_at,
            ]),
            'created_at' => $file->created_at->toDateTimeString(),
            'updated_at' => $file->updated_at->toDateTimeString(),
        ]);
    }
}
