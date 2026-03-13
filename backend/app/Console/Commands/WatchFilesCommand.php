<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFileJob;
use App\Models\FileRecord;
use App\Models\SystemConfig;
use App\Services\FileConverterService;
use Illuminate\Console\Command;

class WatchFilesCommand extends Command
{
    protected $signature = 'files:watch {--interval=5 : Scan interval in seconds}';
    protected $description = 'Watch the IncomeFiles folder for new files and process them';

    public function handle(FileConverterService $converter): int
    {
        $interval = (int) $this->option('interval');

        $this->info('Starting file watcher...');

        while (true) {
            $incomePath = SystemConfig::get('income_files_path', 'C:\\IncomeFiles');

            if (!is_dir($incomePath)) {
                $this->warn("Income path does not exist: {$incomePath}");
                sleep($interval);
                continue;
            }

            $files = glob($incomePath . DIRECTORY_SEPARATOR . '*');

            foreach ($files as $filePath) {
                if (!is_file($filePath)) {
                    continue;
                }

                $filename = basename($filePath);

                // Skip already tracked files
                $exists = FileRecord::where('source_path', $filePath)
                    ->orWhere(function ($q) use ($filename) {
                        $q->where('filename', $filename)->where('folder', 'incoming');
                    })
                    ->exists();

                if ($exists) {
                    continue;
                }

                $this->info("New file detected: {$filename}");

                $fileType = $converter->getFileType($filePath);

                $record = FileRecord::create([
                    'filename' => $filename,
                    'file_type' => $fileType,
                    'file_size' => filesize($filePath),
                    'status' => 'pending',
                    'folder' => 'incoming',
                    'source_path' => $filePath,
                ]);

                ProcessFileJob::dispatch($record->id);

                $this->info("Dispatched processing job for: {$filename} (ID: {$record->id})");
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
