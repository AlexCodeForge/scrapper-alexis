<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\ScraperSettings;
use App\Models\PostingSetting;
use App\Models\SchedulerState;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        /**
         * Database-based scheduler logic
         *
         * WHY DATABASE INSTEAD OF CACHE:
         * - Persistent storage survives cache clears and restarts
         * - Clear audit trail of when jobs ran
         * - Easy to query and debug
         *
         * HOW IT WORKS:
         * 1. System cron runs `schedule:run` every minute
         * 2. For each job, we check the database to see when it last ran
         * 3. If enough time has passed (based on random interval), we run it
         * 4. After running, we pick a NEW random interval for next time
         *
         * RANDOM INTERVAL:
         * - User sets min-max range (e.g., 5-10 minutes)
         * - Each time job runs, we pick random minutes between min-max
         * - This creates natural variance while respecting settings
         */
        $shouldRun = function (string $job, int $minMinutes, int $maxMinutes): bool {
            $state = SchedulerState::getForJob($job);

            // First run or never run before
            if (!$state->last_run_at || !$state->next_interval_minutes) {
                $nextInterval = rand($minMinutes, $maxMinutes);
                $state->markAsRun($nextInterval);

                \Log::info("Scheduler FIRST RUN: {$job}", [
                    'next_interval' => $nextInterval . ' minutes'
                ]);

                return true;
            }

            // Calculate elapsed time since last run
            $minutesSinceLastRun = $state->last_run_at->diffInMinutes();
            $shouldRun = $minutesSinceLastRun >= $state->next_interval_minutes;

            \Log::info("Scheduler check: {$job}", [
                'last_run' => $state->last_run_at->format('Y-m-d H:i:s'),
                'minutes_elapsed' => $minutesSinceLastRun,
                'interval_needed' => $state->next_interval_minutes,
                'should_run' => $shouldRun
            ]);

            if ($shouldRun) {
                // Pick NEW random interval for next run
                $nextInterval = rand($minMinutes, $maxMinutes);
                $state->markAsRun($nextInterval);

                \Log::info("Scheduler RUNNING: {$job}", [
                    'next_interval' => $nextInterval . ' minutes'
                ]);

                return true;
            }

            return false;
        };

        // Facebook Scraper - Dynamic interval from database (UI controlled)
        $schedule->command('scraper:facebook')
            ->everyMinute()
            ->when(function () use ($shouldRun) {
                $settings = ScraperSettings::getSettings();

                if (!$settings->facebook_enabled) {
                    return false;
                }

                return $shouldRun(
                    'facebook',
                    $settings->facebook_interval_min ?? 40,
                    $settings->facebook_interval_max ?? 80
                );
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();

        // =============================================================================
        // Twitter Poster - COMMENTED OUT (NO LONGER USED)
        // =============================================================================
        // Twitter posting functionality has been removed from the application.
        // The app now focuses on image generation only.
        // =============================================================================

        // Facebook Page Poster - Dynamic interval from database (UI controlled)
        $schedule->command('scraper:page-poster')
            ->everyMinute()
            ->when(function () use ($shouldRun) {
                $settings = PostingSetting::getSettings();

                if (!$settings->enabled) {
                    return false;
                }

                return $shouldRun(
                    'page_poster',
                    $settings->interval_min ?? 60,
                    $settings->interval_max ?? 120
                );
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();

        // Image Generator - Dynamic interval from database (UI controlled)
        $schedule->command('scraper:generate-images')
            ->everyMinute()
            ->when(function () use ($shouldRun) {
                $settings = ScraperSettings::getSettings();

                if (!$settings->image_generator_enabled) {
                    return false;
                }

                return $shouldRun(
                    'image_generator',
                    $settings->image_generator_interval_min ?? 30,
                    $settings->image_generator_interval_max ?? 60
                );
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
    })
    ->create();
