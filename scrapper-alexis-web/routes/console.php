<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule cleanup of downloaded images older than 7 days
// Runs daily at 2:00 AM
Schedule::command('app:cleanup-downloaded-images')->daily()->at('02:00');
