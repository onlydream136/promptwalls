<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileRecord;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
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

    public function stats(Request $request): JsonResponse
    {
        $base = fn() => $this->scopedQuery($request);

        $total = $base()->count();
        $sensitive = $base()->whereIn('status', ['sensitive', 'desensitized'])->count();
        $desensitized = $base()->where('status', 'desensitized')->count();
        $noRisk = $base()->where('status', 'no_risk')->count();
        $processing = $base()->whereIn('status', ['pending', 'ocr_scanning', 'assessing'])->count();

        return response()->json([
            'total_files' => $total,
            'sensitive_detected' => $sensitive,
            'desensitized' => $desensitized,
            'no_risk' => $noRisk,
            'processing' => $processing,
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $records = $this->scopedQuery($request)
            ->with('processLogs')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'filename' => $r->filename,
                'file_type' => $r->file_type,
                'status' => $r->status,
                'folder' => $r->folder,
                'created_at' => $r->created_at->toDateTimeString(),
                'updated_at' => $r->updated_at->toDateTimeString(),
                'latest_action' => $r->processLogs->last()?->action,
            ]);

        return response()->json($records);
    }

    public function throughput(Request $request): JsonResponse
    {
        $data = $this->scopedQuery($request)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(*) as count")
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json($data);
    }

    public function monitor(): JsonResponse
    {
        $incomePath = SystemConfig::get('income_files_path', 'C:\\IncomeFiles');

        $fileCount = 0;
        $totalSize = 0;

        if (is_dir($incomePath)) {
            $files = glob($incomePath . DIRECTORY_SEPARATOR . '*');
            $fileCount = count($files);
            $totalSize = array_sum(array_map('filesize', array_filter($files, 'is_file')));
        }

        $lastProcessed = FileRecord::orderByDesc('updated_at')->first();

        return response()->json([
            'path' => $incomePath,
            'status' => is_dir($incomePath) ? 'active' : 'inactive',
            'pending_files' => $fileCount,
            'total_size' => $totalSize,
            'last_scan' => $lastProcessed?->updated_at?->toDateTimeString(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 1),
        ]);
    }
}
