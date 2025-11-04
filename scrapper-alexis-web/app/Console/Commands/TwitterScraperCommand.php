<?php

namespace App\Console\Commands;

use App\Models\ScraperSettings;
use Illuminate\Console\Command;

/**
 * =============================================================================
 * ⚠️  DEPRECATED - NO LONGER USED ⚠️
 * =============================================================================
 * This command is no longer used. Twitter posting functionality has been
 * removed from the application to simplify it to IMAGE GENERATION ONLY.
 * 
 * Users now configure profile information (display name, username, avatar)
 * directly in the web interface settings page instead of posting to Twitter.
 * 
 * The cron schedule for this command has been commented out in bootstrap/app.php
 * 
 * This file is kept for reference only.
 * =============================================================================
 */
class TwitterScraperCommand extends Command
{
    protected $signature = 'scraper:twitter {--skip-delay : Skip random delay for testing} {--manual : Manual execution bypasses enabled check}';
    protected $description = '[DEPRECATED] Run Twitter poster with database credentials and dynamic delays';

    public function handle()
    {
        \Log::info('TwitterScraperCommand: Starting', [
            'skip_delay' => $this->option('skip-delay'),
            'manual' => $this->option('manual')
        ]);

        $settings = ScraperSettings::getSettings();

        // For manual execution, bypass the enabled check
        if (!$this->option('manual') && !$settings->twitter_enabled) {
            $this->warn('Twitter poster is disabled in database');
            \Log::info('TwitterScraperCommand: Skipped (disabled)');
            return 0;
        }

        if ($this->option('manual')) {
            $this->info('Manual execution: bypassing enabled check');
        }

        // Apply random delay unless skipped for testing
        if (!$this->option('skip-delay')) {
            $this->applyRandomDelay(
                $settings->twitter_interval_min,
                $settings->twitter_interval_max
            );
        } else {
            $this->info('Skipping random delay (testing mode)');
        }

        // Run Python script in virtualenv
        $scriptPath = '/var/www/alexis-scrapper-docker/scrapper-alexis';

        $this->info('Starting Twitter poster...');
        \Log::info('TwitterScraperCommand: Executing Python script');

        $exitCode = $this->runInVirtualenv(
            $scriptPath,
            'bash run_twitter_flow.sh'
        );

        if ($exitCode === 0) {
            $this->info('✅ Twitter poster completed successfully');
            \Log::info('TwitterScraperCommand: Completed successfully');
        } else {
            $this->error('❌ Twitter poster failed with exit code: ' . $exitCode);
            \Log::error('TwitterScraperCommand: Failed', ['exit_code' => $exitCode]);
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

        \Log::info('TwitterScraperCommand: Applying random delay', [
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

