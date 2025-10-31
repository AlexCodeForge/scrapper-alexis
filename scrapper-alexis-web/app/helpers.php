<?php

use App\Models\Message;
use Illuminate\Support\Facades\File;

/**
 * Update a key-value pair in the scraper's .env file
 */
function updateEnvFile(string $key, string $value): bool
{
    error_log("updateEnvFile: CALLED with key={$key}, value={$value}");
    // In Docker, scraper folder is mounted at /scraper
    $envPath = '/scraper/.env';

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
 * Update the crontab with new intervals and enabled/disabled status
 * In Docker, cron is managed by the scraper container's entrypoint
 * We just need to restart the scraper container to pick up .env changes
 */
function updateCrontab(int $facebookIntervalMin, int $twitterIntervalMin, bool $facebookEnabled = true, bool $twitterEnabled = true): bool
{
    // In Docker environment, cron is managed by the scraper container
    // The entrypoint script reads .env and sets up cron automatically
    // We restart the scraper container to pick up changes

    try {
        $output = [];
        $returnVar = 0;

        // Restart the scraper container using docker restart (direct container name)
        $command = 'docker restart scraper-alexis-scraper 2>&1';
        exec($command, $output, $returnVar);

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
    // In Docker, scripts run inside the scraper container at /app/
    $scriptsMap = [
        'facebook' => '/app/run_facebook_flow.sh',
        'twitter' => '/app/run_twitter_flow.sh',
        'images' => '/app/run_image_generation.sh',
        'page_poster' => '/app/run_page_poster.sh',
    ];

    if (!isset($scriptsMap[$script])) {
        return ['success' => false, 'message' => 'Invalid script name'];
    }

    $scriptPath = $scriptsMap[$script];

    // Create timestamped log file for this manual run (in scraper container)
    $timestamp = date('YmdHis');
    $logFile = "/app/logs/manual_{$script}_{$timestamp}.log";

    // Execute script inside scraper container using docker exec
    // SKIP_DELAY=1 ensures manual runs execute IMMEDIATELY without random delays
    // Run in background with output redirected to log file
    $command = sprintf(
        'docker exec -d scraper-alexis-scraper bash -c "export SKIP_DELAY=1 && %s > %s 2>&1"',
        escapeshellarg($scriptPath),
        escapeshellarg($logFile)
    );

    // Log the command for debugging
    \Log::info("Executing scraper command in Docker", [
        'command' => $command,
        'script' => $script,
        'container' => 'scraper-alexis-scraper'
    ]);

    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);

    \Log::info("Docker exec command executed", [
        'return_code' => $returnVar,
        'output' => $output
    ]);

    // Give script moment to start
    usleep(500000); // 0.5 seconds

    // Check if log was created (accessible via /scraper/ mount)
    $hostLogPath = "/scraper/logs/manual_{$script}_{$timestamp}.log";

    if ($returnVar === 0) {
        return [
            'success' => true,
            'message' => ucfirst($script) . ' script started successfully in Docker container. Check log: manual_' . $script . '_' . $timestamp . '.log',
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to start script in Docker container. Check Docker daemon and container status.',
        ];
    }
}

/**
 * Get the enabled status of a cronjob from .env file
 */
function getJobStatus(string $job): bool
{
    // In Docker, scraper folder is mounted at /scraper
    $envPath = '/scraper/.env';

    if (!file_exists($envPath)) {
        return true; // Default to enabled if .env doesn't exist
    }

    $jobMap = [
        'facebook' => 'FACEBOOK_SCRAPER_ENABLED',
        'twitter' => 'TWITTER_POSTER_ENABLED',
    ];

    if (!isset($jobMap[$job])) {
        return false;
    }

    $key = $jobMap[$job];
    $envContent = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($envContent as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$envKey, $value] = explode('=', $line, 2);
            if (trim($envKey) === $key) {
                return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN);
            }
        }
    }

    return true; // Default to enabled if key not found
}

/**
 * Get logs for a specific job
 */
function getJobLogs(string $job, int $lines = 100): string
{
    // In Docker, logs are mounted at /scraper/logs/
    $logMap = [
        'facebook' => '/scraper/logs/facebook_cron.log',
        'twitter' => '/scraper/logs/twitter_cron.log',
        'execution' => '/scraper/logs/cron_execution.log',
    ];

    if (!isset($logMap[$job])) {
        return "Invalid job type: {$job}";
    }

    $logFile = $logMap[$job];

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
    // In Docker, logs are mounted at /scraper/logs/
    $logsDir = '/scraper/logs';

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
    // In Docker, logs are mounted at /scraper/logs/
    $logFile = '/scraper/logs/' . basename($filename);

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

