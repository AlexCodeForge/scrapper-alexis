<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupDownloadedImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-downloaded-images {--days=7 : Number of days after which to delete downloaded images}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete downloaded images older than specified days (default: 7 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Starting cleanup of downloaded images older than {$days} days (before {$cutoffDate->toDateTimeString()})");
        Log::info('CleanupDownloadedImages: Starting cleanup', ['days' => $days, 'cutoff_date' => $cutoffDate->toDateTimeString()]);

        // Find messages with downloaded images older than cutoff date
        $messages = Message::where('downloaded', true)
            ->where('image_generated', true)
            ->whereNotNull('downloaded_at')
            ->where('downloaded_at', '<', $cutoffDate)
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No images found to delete.');
            Log::info('CleanupDownloadedImages: No images to delete');
            return 0;
        }

        $this->info("Found {$messages->count()} images to delete.");
        Log::info('CleanupDownloadedImages: Found images to delete', ['count' => $messages->count()]);

        $deletedCount = 0;
        $skippedCount = 0;
        $totalSize = 0;

        foreach ($messages as $message) {
            $imagePath = $message->image_full_path;

            if ($imagePath && file_exists($imagePath)) {
                $fileSize = filesize($imagePath);

                // Delete the file
                if (unlink($imagePath)) {
                    // Update database
                    $message->update([
                        'image_generated' => false,
                        'image_path' => null,
                    ]);

                    $deletedCount++;
                    $totalSize += $fileSize;

                    Log::info('CleanupDownloadedImages: Deleted image', [
                        'message_id' => $message->id,
                        'path' => $imagePath,
                        'size' => $fileSize,
                        'downloaded_at' => $message->downloaded_at?->toDateTimeString()
                    ]);
                } else {
                    $skippedCount++;
                    Log::error('CleanupDownloadedImages: Failed to delete image file', [
                        'message_id' => $message->id,
                        'path' => $imagePath
                    ]);
                }
            } else {
                // File doesn't exist, just update database
                $message->update([
                    'image_generated' => false,
                    'image_path' => null,
                ]);

                $skippedCount++;
                Log::warning('CleanupDownloadedImages: Image file not found, updated database', [
                    'message_id' => $message->id,
                    'path' => $imagePath
                ]);
            }
        }

        $totalSizeMB = round($totalSize / 1024 / 1024, 2);

        $this->info("Cleanup complete:");
        $this->info("  - Deleted: {$deletedCount} images");
        $this->info("  - Skipped: {$skippedCount} images");
        $this->info("  - Space freed: {$totalSizeMB} MB");

        Log::info('CleanupDownloadedImages: Cleanup complete', [
            'deleted' => $deletedCount,
            'skipped' => $skippedCount,
            'total_size_mb' => $totalSizeMB
        ]);

        return 0;
    }
}
