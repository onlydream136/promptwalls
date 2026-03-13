<?php

namespace App\Jobs;

use App\Models\FileRecord;
use App\Models\ProcessLog;
use App\Models\SystemConfig;
use App\Services\AssessorService;
use App\Services\DesensitizerService;
use App\Services\FileConverterService;
use App\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;  // We handle retries ourselves
    public int $timeout = 600;

    private const MAX_RETRIES = 2;

    public function __construct(
        private int $fileRecordId
    ) {}

    public function handle(
        FileConverterService $converter,
        OcrService $ocr,
        AssessorService $assessor,
        DesensitizerService $desensitizer,
    ): void {
        $file = FileRecord::find($this->fileRecordId);

        if (!$file || !file_exists($file->source_path)) {
            Log::warning("File not found for processing", ['id' => $this->fileRecordId]);
            return;
        }

        try {
            // Step 1: Extract text (OCR for images, converter for documents)
            $file->update(['status' => 'ocr_scanning']);
            ProcessLog::create([
                'file_record_id' => $file->id,
                'action' => 'ocr',
                'details' => 'Starting text extraction (attempt ' . ($file->retry_count + 1) . ')',
            ]);

            if ($converter->isImage($file->source_path)) {
                $text = $ocr->recognize($file->source_path);
            } else {
                $text = $converter->convertToText($file->source_path);
            }

            $file->update(['extracted_text' => $text]);

            if (empty(trim($text))) {
                $file->update([
                    'status' => 'no_risk',
                    'folder' => 'clean',
                ]);
                $this->moveFile($file, 'no_sensitive_path');
                ProcessLog::create([
                    'file_record_id' => $file->id,
                    'action' => 'assess',
                    'details' => 'No text extracted - marked as clean',
                ]);
                return;
            }

            // Step 2: Assess content for sensitive information
            $file->update(['status' => 'assessing']);
            ProcessLog::create([
                'file_record_id' => $file->id,
                'action' => 'assess',
                'details' => 'Starting content assessment',
            ]);

            $assessment = $assessor->assess($text);
            $file->update(['assessment_result' => $assessment]);

            // Step 3: Route based on assessment
            if (empty($assessment['has_sensitive']) || $assessment['has_sensitive'] === false) {
                // No sensitive info - move to clean folder
                $file->update([
                    'status' => 'no_risk',
                    'folder' => 'clean',
                ]);
                $this->moveFile($file, 'no_sensitive_path');

                ProcessLog::create([
                    'file_record_id' => $file->id,
                    'action' => 'assess',
                    'details' => 'No sensitive information detected - moved to clean',
                ]);
            } else {
                // Sensitive info detected
                $file->update([
                    'status' => 'sensitive',
                    'folder' => 'sensitive',
                ]);
                $this->moveFile($file, 'sensitive_files_path');

                ProcessLog::create([
                    'file_record_id' => $file->id,
                    'action' => 'assess',
                    'details' => "Sensitive info detected: {$assessment['summary']}",
                ]);

                // Step 4: Desensitize
                $desensitizedText = $desensitizer->desensitize($file, $text, $assessment);

                // Save desensitized version
                $desensitizedPath = SystemConfig::get('desensitized_files_path', 'C:\\DesentizedFiles');
                $outputFile = $desensitizedPath . DIRECTORY_SEPARATOR . 'desensitized_' . $file->filename . '.txt';

                if (!is_dir($desensitizedPath)) {
                    mkdir($desensitizedPath, 0755, true);
                }

                file_put_contents($outputFile, $desensitizedText);

                $file->update([
                    'status' => 'desensitized',
                    'folder' => 'desensitized',
                    'output_path' => $outputFile,
                ]);

                ProcessLog::create([
                    'file_record_id' => $file->id,
                    'action' => 'desensitize',
                    'details' => "Desensitized file saved: {$outputFile}",
                ]);
            }
        } catch (\Exception $e) {
            Log::error('File processing failed', [
                'file_id' => $file->id,
                'attempt' => $file->retry_count + 1,
                'error' => $e->getMessage(),
            ]);

            $newRetryCount = $file->retry_count + 1;

            if ($newRetryCount >= self::MAX_RETRIES) {
                // Max retries reached - mark as failed
                $file->update([
                    'status' => 'failed',
                    'folder' => 'incoming',
                    'retry_count' => $newRetryCount,
                ]);

                ProcessLog::create([
                    'file_record_id' => $file->id,
                    'action' => 'error',
                    'details' => "Processing failed after {$newRetryCount} attempts: {$e->getMessage()}",
                ]);
            } else {
                // Still have retries left - increment count and re-dispatch
                $file->update([
                    'retry_count' => $newRetryCount,
                ]);

                ProcessLog::create([
                    'file_record_id' => $file->id,
                    'action' => 'error',
                    'details' => "Attempt {$newRetryCount} failed, retrying: {$e->getMessage()}",
                ]);

                // Re-dispatch the job for another attempt
                self::dispatch($this->fileRecordId)->delay(now()->addSeconds(5));
            }
        }
    }

    /**
     * Move file to the configured destination folder.
     */
    private function moveFile(FileRecord $file, string $configKey): void
    {
        $destDir = SystemConfig::get($configKey);

        if (!$destDir || !file_exists($file->source_path)) {
            return;
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = $destDir . DIRECTORY_SEPARATOR . basename($file->source_path);
        copy($file->source_path, $destPath);

        $file->update(['output_path' => $destPath]);
    }
}
