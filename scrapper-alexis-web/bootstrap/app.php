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
        /**
         * BUGFIX: This callback now ONLY checks if the job should run.
         * State is updated AFTER successful execution via onSuccess() callback.
         * This prevents the "ghost run" bug where state updates but command never executes.
         */
        $shouldRunCheck = function (string $job): bool {
            try {
                $state = SchedulerState::getForJob($job);

                // First run or never run before
                if (!$state->last_run_at || !$state->next_interval_minutes) {
                    return true;
                }

                // Calculate elapsed time since last run
                $minutesSinceLastRun = $state->last_run_at->diffInMinutes();
                $shouldRun = $minutesSinceLastRun >= $state->next_interval_minutes;

                return $shouldRun;
            } catch (\Exception $e) {
                // If there's any error, allow the job to run
                return true;
            }
        };

        /**
         * Closure to update state AFTER successful job execution
         * This ensures state only updates when the job actually completes successfully
         */
        $updateStateOnSuccess = function (string $job, int $minMinutes, int $maxMinutes) {
            return function () use ($job, $minMinutes, $maxMinutes) {
                try {
                    $state = SchedulerState::getForJob($job);
                    $nextInterval = rand($minMinutes, $maxMinutes);
                    $state->markAsRun($nextInterval);
                } catch (\Exception $e) {
                    // Silently fail - better to continue than to break the scheduler
                }
            };
        };

        // Facebook Scraper - Dynamic interval from database (UI controlled)
        $schedule->command('scraper:facebook --skip-delay')
            ->everyMinute()
            ->when(function () {
                try {
                    $settings = ScraperSettings::getSettings();
                    if (!$settings->facebook_enabled) {
                        return false;
                    }

                    $state = SchedulerState::getForJob('facebook');
                    if (!$state->last_run_at || !$state->next_interval_minutes) {
                        return true; // First run
                    }

                    $minutesSinceLastRun = $state->last_run_at->diffInMinutes();
                    return $minutesSinceLastRun >= $state->next_interval_minutes;
                } catch (\Exception $e) {
                    return true; // On error, allow run
                }
            })
            ->onSuccess(function () {
                try {
                    $settings = ScraperSettings::getSettings();
                    $state = SchedulerState::getForJob('facebook');
                    $nextInterval = rand($settings->facebook_interval_min ?? 40, $settings->facebook_interval_max ?? 80);
                    $state->markAsRun($nextInterval);
                } catch (\Exception $e) {
                    // Silently fail
                }
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
        $schedule->command('scraper:page-poster --skip-delay')
            ->everyMinute()
            ->when(function () {
                try {
                    $settings = PostingSetting::getSettings();
                    if (!$settings->enabled) {
                        return false;
                    }

                    $state = SchedulerState::getForJob('page_poster');
                    if (!$state->last_run_at || !$state->next_interval_minutes) {
                        return true; // First run
                    }

                    $minutesSinceLastRun = $state->last_run_at->diffInMinutes();
                    return $minutesSinceLastRun >= $state->next_interval_minutes;
                } catch (\Exception $e) {
                    return true; // On error, allow run
                }
            })
            ->onSuccess(function () {
                try {
                    $settings = PostingSetting::getSettings();
                    $state = SchedulerState::getForJob('page_poster');
                    $nextInterval = rand($settings->interval_min ?? 60, $settings->interval_max ?? 120);
                    $state->markAsRun($nextInterval);
                } catch (\Exception $e) {
                    // Silently fail
                }
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();

        // Image Generator - Dynamic interval from database (UI controlled)
        $schedule->command('scraper:generate-images --skip-delay')
            ->everyMinute()
            ->when(function () {
                try {
                    $settings = ScraperSettings::getSettings();
                    if (!$settings->image_generator_enabled) {
                        return false;
                    }

                    $state = SchedulerState::getForJob('image_generator');
                    if (!$state->last_run_at || !$state->next_interval_minutes) {
                        return true; // First run
                    }

                    $minutesSinceLastRun = $state->last_run_at->diffInMinutes();
                    return $minutesSinceLastRun >= $state->next_interval_minutes;
                } catch (\Exception $e) {
                    return true; // On error, allow run
                }
            })
            ->onSuccess(function () {
                try {
                    $settings = ScraperSettings::getSettings();
                    $state = SchedulerState::getForJob('image_generator');
                    $nextInterval = rand($settings->image_generator_interval_min ?? 30, $settings->image_generator_interval_max ?? 60);
                    $state->markAsRun($nextInterval);
                } catch (\Exception $e) {
                    // Silently fail
                }
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
    })
    ->create();
