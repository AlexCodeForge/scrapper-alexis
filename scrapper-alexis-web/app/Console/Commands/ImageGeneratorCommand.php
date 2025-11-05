<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImageGeneratorCommand extends Command
{
    protected $signature = 'scraper:generate-images {--skip-delay : Skip random delay for testing} {--manual : Manual execution bypasses enabled check}';
    protected $description = 'Generate images for approved messages';

    public function handle()
    {
        \Log::info('ImageGeneratorCommand: Starting', [
            'skip_delay' => $this->option('skip-delay'),
            'manual' => $this->option('manual')
        ]);

        if ($this->option('manual')) {
            $this->info('Manual execution: generating images for approved messages');
        }

        // Run Python script in virtualenv - use dynamic path from config
        $pythonPath = config('scraper.python_path');
        $scriptPath = $pythonPath . '/' . 'scrapper-alexis';

        $this->info('Starting image generator...');
        \Log::info('ImageGeneratorCommand: Executing Python script', ['path' => $scriptPath]);

        $exitCode = $this->runInVirtualenv(
            $scriptPath,
            'bash run_image_generation.sh'
        );

        if ($exitCode === 0) {
            $this->info('✅ Image generator completed successfully');
            \Log::info('ImageGeneratorCommand: Completed successfully');
        } else {
            $this->error('❌ Image generator failed with exit code: ' . $exitCode);
            \Log::error('ImageGeneratorCommand: Failed', ['exit_code' => $exitCode]);
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

