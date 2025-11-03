<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\ScraperSettings;
use App\Models\PostingSetting;

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
        // Facebook Scraper - Hourly with database control
        $schedule->command('scraper:facebook')
            ->hourly()
            ->when(function () {
                return ScraperSettings::getSettings()->facebook_enabled;
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Twitter Poster - Hourly with 15min offset
        $schedule->command('scraper:twitter')
            ->hourly()
            ->at(':15')
            ->when(function () {
                return ScraperSettings::getSettings()->twitter_enabled;
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
        
        // Facebook Page Poster - Every 30 minutes
        $schedule->command('scraper:page-poster')
            ->everyThirtyMinutes()
            ->when(function () {
                return PostingSetting::getSettings()->enabled;
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer();
    })
    ->create();
