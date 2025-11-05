<?php

namespace App\Console\Commands;

use App\Models\PostingSetting;
use Illuminate\Console\Command;

class PagePosterCommand extends Command
{
    protected $signature = 'scraper:page-poster {--skip-delay : Skip random delay for testing} {--manual : Manual execution bypasses enabled check}';
    protected $description = 'Run Facebook page poster with database credentials';

    public function handle()
    {
        \Log::info('PagePosterCommand: Starting', [
            'skip_delay' => $this->option('skip-delay'),
            'manual' => $this->option('manual')
        ]);

        $settings = PostingSetting::getSettings();

        // For manual execution, bypass the enabled check
        if (!$this->option('manual') && !$settings->enabled) {
            $this->warn('Facebook page poster is disabled in database');
            \Log::info('PagePosterCommand: Skipped (disabled)');
            return 0;
        }

        if ($this->option('manual')) {
            $this->info('Manual execution: bypassing enabled check');
        }

        // Run Python script in virtualenv - use dynamic path from config
        $pythonPath = config('scraper.python_path');
        $scriptPath = $pythonPath . '/' . 'scrapper-alexis';

        $this->info('Starting Facebook page poster...');
        \Log::info('PagePosterCommand: Executing Python script', ['path' => $scriptPath]);

        $exitCode = $this->runInVirtualenv(
            $scriptPath,
            'bash run_page_poster.sh'
        );

        if ($exitCode === 0) {
            $this->info('✅ Facebook page poster completed successfully');
            \Log::info('PagePosterCommand: Completed successfully');
        } else {
            $this->error('❌ Facebook page poster failed with exit code: ' . $exitCode);
            \Log::error('PagePosterCommand: Failed', ['exit_code' => $exitCode]);
        }

        return $exitCode;
    }

    private function runInVirtualenv(string $scriptPath, string $command): int
    {
        $fullCommand = sprintf(
            'sudo -u root /bin/bash -c "cd %s && source venv/bin/activate && %s" 2>&1',
            escapeshellarg($scriptPath),
            $command
        );

        passthru($fullCommand, $exitCode);

        return $exitCode;
    }
}

