<?php

namespace App\Console\Commands;

use App\Models\ScraperSettings;
use Illuminate\Console\Command;

class FacebookScraperCommand extends Command
{
    protected $signature = 'scraper:facebook {--skip-delay : Skip random delay for testing} {--manual : Manual execution bypasses enabled check}';
    protected $description = 'Run Facebook scraper with database credentials and dynamic delays';

    public function handle()
    {
        \Log::info('FacebookScraperCommand: Starting', [
            'skip_delay' => $this->option('skip-delay'),
            'manual' => $this->option('manual')
        ]);

        $settings = ScraperSettings::getSettings();

        // For manual execution, bypass the enabled check
        if (!$this->option('manual') && !$settings->facebook_enabled) {
            $this->warn('Facebook scraper is disabled in database');
            \Log::info('FacebookScraperCommand: Skipped (disabled)');
            return 0;
        }

        if ($this->option('manual')) {
            $this->info('Manual execution: bypassing enabled check');
        }

        // Apply random delay unless skipped for testing
        if (!$this->option('skip-delay')) {
            $this->applyRandomDelay(
                $settings->facebook_interval_min,
                $settings->facebook_interval_max
            );
        } else {
            $this->info('Skipping random delay (testing mode)');
        }

        // Run Python script in virtualenv
        $scriptPath = '/var/www/alexis-scrapper-docker/scrapper-alexis';

        $this->info('Starting Facebook scraper...');
        \Log::info('FacebookScraperCommand: Executing Python script');

        $exitCode = $this->runInVirtualenv(
            $scriptPath,
            'xvfb-run -a python3 relay_agent.py'
        );

        if ($exitCode === 0) {
            $this->info('✅ Facebook scraper completed successfully');
            \Log::info('FacebookScraperCommand: Completed successfully');
        } else {
            $this->error('❌ Facebook scraper failed with exit code: ' . $exitCode);
            \Log::error('FacebookScraperCommand: Failed', ['exit_code' => $exitCode]);
        }

        return $exitCode;
    }

    private function applyRandomDelay(int $min, int $max): void
    {
        $avgInterval = ($min + $max) / 2;
        $avgSeconds = $avgInterval * 60;
        $maxDelay = (int) ($avgSeconds * 0.20); // 20% variance for natural behavior

        $delay = rand(0, $maxDelay);

        $this->info(sprintf(
            'Interval: %d-%d min, applying random delay: %d seconds (%d min %d sec)',
            $min,
            $max,
            $delay,
            floor($delay / 60),
            $delay % 60
        ));

        \Log::info('FacebookScraperCommand: Applying random delay', [
            'interval_min' => $min,
            'interval_max' => $max,
            'delay_seconds' => $delay
        ]);

        sleep($delay);
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

