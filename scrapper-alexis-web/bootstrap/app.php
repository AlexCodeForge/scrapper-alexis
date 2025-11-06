<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\ScraperSettings;
use App\Models\PostingSetting;
use Illuminate\Support\Facades\Cache;

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
        // Helper function to check if enough time has passed based on dynamic interval
        $shouldRun = function (string $job, int $minMinutes, int $maxMinutes): bool {
            $lastRunKey = "scheduler_last_run_{$job}";
            $intervalKey = "scheduler_next_interval_{$job}";
            
            $lastRun = Cache::get($lastRunKey);
            $nextInterval = Cache::get($intervalKey);
            
            // First run or cache cleared
            if (!$lastRun || !$nextInterval) {
                // Set random interval for next run
                $nextInterval = rand($minMinutes, $maxMinutes);
                Cache::put($intervalKey, $nextInterval, now()->addHours(24));
                Cache::put($lastRunKey, now(), now()->addHours(24));
                return true;
            }
            
            // Check if enough time has passed
            $minutesSinceLastRun = now()->diffInMinutes($lastRun);
            
            if ($minutesSinceLastRun >= $nextInterval) {
                // Time to run - set new random interval for next time
                $nextInterval = rand($minMinutes, $maxMinutes);
                Cache::put($intervalKey, $nextInterval, now()->addHours(24));
                Cache::put($lastRunKey, now(), now()->addHours(24));
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
