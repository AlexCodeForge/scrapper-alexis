<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dynamically set timezone from database
        try {
            $settings = \App\Models\ScraperSettings::getSettings();
            if ($settings && $settings->timezone) {
                date_default_timezone_set($settings->timezone);
                config(['app.timezone' => $settings->timezone]);
            }
        } catch (\Exception $e) {
            // Fallback to config default if database is not available (during migrations, etc.)
            // Log this for debugging but don't fail the boot process
            \Log::debug('Could not load timezone from database: ' . $e->getMessage());
        }
    }
}
