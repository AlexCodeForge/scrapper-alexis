<?php

namespace App\Services;

use App\Models\Message;
use App\Models\PostingSetting;
use Illuminate\Support\Collection;

class PostingService
{
    /**
     * Get pending images for approval (with generated images, not approved yet)
     */
    public function getPendingImages(int $limit = 50): Collection
    {
        return Message::withImages()
            ->pendingApproval()
            ->latest('scraped_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get approved images ready for posting (approved but not posted yet)
     * Only includes auto-post enabled images for cron job
     */
    public function getApprovedImages(): Collection
    {
        return Message::withImages()
            ->approvedForPosting()
            ->autoPostEnabled()
            ->notPostedToPage()
            ->oldest('approved_at')
            ->get();
    }

    /**
     * Get approved images for manual posting queue
     */
    public function getManualPostImages(): Collection
    {
        return Message::withImages()
            ->approvedForPosting()
            ->manualPostOnly()
            ->notPostedToPage()
            ->oldest('approved_at')
            ->get();
    }

    /**
     * Approve an image for posting (defaults to auto-post)
     */
    public function approveImage(int $messageId): bool
    {
        return $this->approveForAutoPost($messageId);
    }

    /**
     * Approve an image for auto-posting (will be posted automatically by cron)
     */
    public function approveForAutoPost(int $messageId): bool
    {
        $message = Message::find($messageId);

        if (!$message) {
            return false;
        }

        return $message->update([
            'approved_for_posting' => true,
            'approved_at' => now(),
            'auto_post_enabled' => true,
            'approval_type' => 'auto',
        ]);
    }

    /**
     * Approve an image for manual posting (won't be auto-posted by cron)
     */
    public function approveForManualPost(int $messageId): bool
    {
        $message = Message::find($messageId);

        if (!$message) {
            return false;
        }

        return $message->update([
            'approved_for_posting' => true,
            'approved_at' => now(),
            'auto_post_enabled' => false,
            'approval_type' => 'manual',
        ]);
    }

    /**
     * Reject an image (mark as not approved)
     */
    public function rejectImage(int $messageId): bool
    {
        $message = Message::find($messageId);

        if (!$message) {
            return false;
        }

        return $message->update([
            'approved_for_posting' => false,
            'approved_at' => now(), // Mark when it was rejected
        ]);
    }

    /**
     * Get statistics for page posting
     */
    public function getPageStats(): array
    {
        return [
            'pending' => Message::withImages()->pendingApproval()->count(),
            'approved_auto' => Message::withImages()->approvedForPosting()->autoPostEnabled()->notPostedToPage()->count(),
            'approved_manual' => Message::withImages()->approvedForPosting()->manualPostOnly()->notPostedToPage()->count(),
            'approved' => Message::withImages()->approvedForPosting()->notPostedToPage()->count(), // Total approved
            'rejected' => Message::withImages()
                ->where('approved_for_posting', false)
                ->whereNotNull('approved_at')
                ->count(),
            'posted' => Message::withImages()->postedToPage()->count(),
        ];
    }

    /**
     * Get posting settings
     */
    public function getSettings(): PostingSetting
    {
        return PostingSetting::getSettings();
    }

    /**
     * Update posting settings
     */
    public function updateSettings(array $data): bool
    {
        return PostingSetting::updateSettings($data);
    }

    /**
     * Get the next approved image to post (one at a time for cronjob)
     * Only returns auto-post enabled images
     */
    public function getNextImageToPost(): ?Message
    {
        return Message::withImages()
            ->approvedForPosting()
            ->autoPostEnabled()
            ->notPostedToPage()
            ->oldest('approved_at')
            ->first();
    }

    /**
     * Mark an image as posted to page
     */
    public function markAsPosted(int $messageId): bool
    {
        $message = Message::find($messageId);

        if (!$message) {
            return false;
        }

        return $message->update([
            'posted_to_page' => true,
            'posted_to_page_at' => now(),
        ]);
    }
}

