<?php

use App\Models\Message;
use Illuminate\Support\Facades\File;

/**
 * DEPRECATED: Update a key-value pair in the scraper's .env file
 * NOTE: This function is deprecated and should not be used.
 * All dynamic settings should be stored in the database via ScraperSettings model.
 */
function updateEnvFile(string $key, string $value): bool
{
    error_log("updateEnvFile: CALLED with key={$key}, value={$value}");
    // NOTE: Running on Nginx, not Docker
    // Dynamic path from config
    $pythonPath = config('scraper.python_path');
    $envPath = $pythonPath . '/scrapper-alexis/.env';

    if (!file_exists($envPath)) {
        error_log("updateEnvFile: FILE NOT FOUND at {$envPath}");
        \Log::error("updateEnvFile: .env file not found", ['path' => $envPath]);
        return false;
    }

    error_log("updateEnvFile: File exists, checking writability");
    // Bugfix: Check if file is writable before attempting write
    if (!is_writable($envPath)) {
        error_log("updateEnvFile: FILE NOT WRITABLE");
        \Log::error("updateEnvFile: .env file is not writable", [
            'path' => $envPath,
            'permissions' => substr(sprintf('%o', fileperms($envPath)), -4),
            'owner' => posix_getpwuid(fileowner($envPath))['name'] ?? 'unknown',
            'current_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'unknown'
        ]);
        return false;
    }
    error_log("updateEnvFile: File is writable, proceeding");

    $envContent = file_get_contents($envPath);
    if ($envContent === false) {
        \Log::error("updateEnvFile: Failed to read .env file", ['path' => $envPath]);
        return false;
    }

    $pattern = "/^{$key}=.*/m";

    if (preg_match($pattern, $envContent)) {
        // Key exists, update it
        $newContent = preg_replace($pattern, "{$key}={$value}", $envContent);
    } else {
        // Key doesn't exist, append it
        $newContent = $envContent . "\n{$key}={$value}\n";
    }

    // Bugfix: Attempt write with retry mechanism for transient permission issues
    $maxRetries = 3;
    $retryDelay = 100000; // 100ms in microseconds

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = @file_put_contents($envPath, $newContent);

        if ($result !== false) {
            \Log::info("updateEnvFile: Successfully updated .env", [
                'key' => $key,
                'attempt' => $attempt
            ]);
            return true;
        }

        $error = error_get_last();
        \Log::warning("updateEnvFile: Write attempt {$attempt}/{$maxRetries} failed", [
            'path' => $envPath,
            'key' => $key,
            'error' => $error['message'] ?? 'unknown',
            'permissions' => substr(sprintf('%o', fileperms($envPath)), -4)
        ]);

        if ($attempt < $maxRetries) {
            usleep($retryDelay);
        }
    }

    \Log::error("updateEnvFile: Failed to write .env file after {$maxRetries} attempts", [
        'path' => $envPath,
        'key' => $key
    ]);
    return false;
}

/**
 * DEPRECATED: Update the crontab with new intervals and enabled/disabled status
 * NOTE: This function is deprecated. Cron jobs are now managed by Laravel Scheduler.
 * All cron settings are stored in the database and checked dynamically.
 * The scheduler runs via system crontab: * * * * * php artisan schedule:run
 */
function updateCrontab(int $facebookIntervalMin, int $twitterIntervalMin, bool $facebookEnabled = true, bool $twitterEnabled = true): bool
{
    // NOTE: This function is deprecated
    // Cron jobs are now managed by Laravel Scheduler in bootstrap/app.php
    // Settings are stored in database and checked dynamically

    try {
        $output = [];
        $returnVar = 0;

        // DEPRECATED: This used to restart Docker container
        // Now we just log a warning
        \Log::warning('updateCrontab: Deprecated function called', [
            'facebook_interval' => $facebookIntervalMin,
            'twitter_interval' => $twitterIntervalMin,
            'facebook_enabled' => $facebookEnabled,
            'twitter_enabled' => $twitterEnabled
        ]);

        return true; // Return true to avoid breaking existing code

        if ($returnVar === 0) {
            \Log::info('updateCrontab: Scraper container restarted successfully', ['output' => $output]);
            return true;
        } else {
            \Log::warning('updateCrontab: Failed to restart scraper container', [
                'return_code' => $returnVar,
                'output' => $output,
                'command' => $command
            ]);
            return false;
        }
    } catch (\Exception $e) {
        \Log::error('updateCrontab: Exception during container restart', ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Download multiple images as a ZIP file
 */
function downloadImagesAsZip(array $messageIds): ?string
{
    $messages = Message::whereIn('id', $messageIds)
        ->where('image_generated', true)
        ->get();

    if ($messages->isEmpty()) {
        return null;
    }

    $zipFileName = 'images_' . date('Y-m-d_H-i-s') . '.zip';
    $zipPath = storage_path('app/public/' . $zipFileName);

    // Ensure storage directory exists
    if (!file_exists(storage_path('app/public'))) {
        mkdir(storage_path('app/public'), 0755, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }

    foreach ($messages as $message) {
        $imagePath = $message->image_full_path;
        if ($imagePath && file_exists($imagePath)) {
            $zip->addFile($imagePath, basename($imagePath));
        }
    }

    $zip->close();

    \Log::info('downloadImagesAsZip: Created zip file', ['zip_path' => $zipPath, 'message_count' => count($messageIds)]);

    return $zipPath;
}

/**
 * Delete images from filesystem and update database
 */
function deleteImages(array $messageIds): int
{
    $messages = Message::whereIn('id', $messageIds)
        ->where('image_generated', true)
        ->get();

    $deletedCount = 0;

    foreach ($messages as $message) {
        $imagePath = $message->image_full_path;

        if ($imagePath && file_exists($imagePath)) {
            // Delete the file
            if (unlink($imagePath)) {
                // Update database
                $message->update([
                    'image_generated' => false,
                    'image_path' => null,
                ]);
                $deletedCount++;
            }
        } else {
            // File doesn't exist, just update database
            $message->update([
                'image_generated' => false,
                'image_path' => null,
            ]);
            $deletedCount++;
        }
    }

    return $deletedCount;
}

/**
 * Execute a scraper script manually with proper permissions
 * Uses sudo to run as root with full environment
 */
function runScraperScript(string $script): array
{
    // Map script names to Artisan commands (no Docker, running on Nginx)
    $commandsMap = [
        'facebook' => 'scraper:facebook',
        'twitter' => 'scraper:twitter',
        'page_poster' => 'scraper:page-poster',
        'image_generator' => 'scraper:generate-images',
    ];

    if (!isset($commandsMap[$script])) {
        return ['success' => false, 'message' => 'Invalid script name'];
    }

    $command = $commandsMap[$script];

    // Create timestamped log file for this manual run - dynamic from config
    $timestamp = date('YmdHis');
    $pythonPath = config('scraper.python_path');
    $logDir = $pythonPath . '/' . config('scraper.logs_dir');
    $logFile = "{$logDir}/manual_{$script}_{$timestamp}.log";

    // Ensure log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Log the command for debugging
    \Log::info("Executing scraper command", [
        'command' => $command,
        'script' => $script,
        'log_file' => $logFile
    ]);

    try {
        // Run Artisan command in background with --manual and --skip-delay flags
        // This bypasses the enabled check and skips random delays for manual execution
        $baseCommand = sprintf(
            'cd %s && php artisan %s --manual --skip-delay >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg($command),
            escapeshellarg($logFile)
        );

        exec($baseCommand, $output, $returnVar);

        \Log::info("Manual script execution started", [
            'return_code' => $returnVar,
            'output' => $output,
            'log' => $logFile
        ]);

        // Give script moment to start and write to log
        usleep(500000); // 0.5 seconds

        if ($returnVar === 0 || file_exists($logFile)) {
            return [
                'success' => true,
                'message' => '✅ ' . ucfirst($script) . ' script started successfully. Check log: manual_' . $script . '_' . $timestamp . '.log',
            ];
        } else {
            return [
                'success' => false,
                'message' => '❌ Failed to start script. Check Laravel logs for details.',
            ];
        }
    } catch (\Exception $e) {
        \Log::error("Manual script execution failed", [
            'script' => $script,
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'message' => '❌ Error: ' . $e->getMessage(),
        ];
    }
}

/**
 * Get the enabled status of a cronjob from database
 * No longer uses .env file - database is the single source of truth
 */
function getJobStatus(string $job): bool
{
    try {
        $jobMap = [
            'facebook' => ['model' => \App\Models\ScraperSettings::class, 'field' => 'facebook_enabled'],
            'twitter' => ['model' => \App\Models\ScraperSettings::class, 'field' => 'twitter_enabled'],
            'page-poster' => ['model' => \App\Models\PostingSetting::class, 'field' => 'enabled'],
            'image-generator' => ['model' => \App\Models\ScraperSettings::class, 'field' => 'image_generator_enabled'],
        ];

        if (!isset($jobMap[$job])) {
            return false;
        }

        $modelClass = $jobMap[$job]['model'];
        $field = $jobMap[$job]['field'];
        $settings = $modelClass::getSettings();

        return (bool) $settings->$field;
    } catch (\Exception $e) {
        \Log::error('getJobStatus failed', ['job' => $job, 'error' => $e->getMessage()]);
        return false; // Default to disabled if database read fails
    }
}

/**
 * Get logs for a specific job
 */
function getJobLogs(string $job, int $lines = 100): string
{
    // Get log file path from configuration
    $logFiles = config('scraper.log_files', []);

    if (!isset($logFiles[$job])) {
        return "Invalid job type: {$job}";
    }

    // Build full path: python_path/logs_dir/log_file
    $pythonPath = config('scraper.python_path');
    $logsDir = config('scraper.logs_dir');
    $logFile = $pythonPath . '/' . $logsDir . '/' . $logFiles[$job];

    if (!file_exists($logFile)) {
        return "Log file not found: {$logFile}\n\nThe job may not have run yet.";
    }

    // Get last N lines using tail command
    $output = [];
    $command = sprintf('tail -n %d %s 2>&1', $lines, escapeshellarg($logFile));
    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        return "Error reading log file. Check permissions.";
    }

    if (empty($output)) {
        return "Log file is empty. The job may not have run yet.";
    }

    return implode("\n", $output);
}

/**
 * Get list of available manual run log files
 */
function getManualLogFiles(): array
{
    // Manual logs are stored in scraper logs directory
    $pythonPath = config('scraper.python_path');
    $logsDir = $pythonPath . '/' . config('scraper.logs_dir');

    if (!is_dir($logsDir)) {
        return [];
    }

    $files = glob($logsDir . '/manual_*.log');

    if ($files === false) {
        return [];
    }

    // Sort by modification time, newest first
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    return array_map(function($file) {
        return [
            'name' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'modified' => filemtime($file),
        ];
    }, $files);
}

/**
 * Get content of a specific log file
 */
function getLogFileContent(string $filename, int $lines = 500): string
{
    // Manual logs are stored in scraper logs directory
    $pythonPath = config('scraper.python_path');
    $logsDir = config('scraper.logs_dir');
    $logFile = $pythonPath . '/' . $logsDir . '/' . basename($filename);

    if (!file_exists($logFile)) {
        return "Log file not found: {$filename}";
    }

    // Get last N lines
    $output = [];
    $command = sprintf('tail -n %d %s 2>&1', $lines, escapeshellarg($logFile));
    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        return "Error reading log file.";
    }

    if (empty($output)) {
        return "Log file is empty.";
    }

    return implode("\n", $output);
}

