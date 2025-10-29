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
     */
    public function getApprovedImages(): Collection
    {
        return Message::withImages()
            ->approvedForPosting()
            ->notPostedToPage()
            ->oldest('approved_at')
            ->get();
    }

    /**
     * Approve an image for posting
     */
    public function approveImage(int $messageId): bool
    {
        $message = Message::find($messageId);

        if (!$message) {
            return false;
        }

        return $message->update([
            'approved_for_posting' => true,
            'approved_at' => now(),
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
            'approved' => Message::withImages()->approvedForPosting()->notPostedToPage()->count(),
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
     */
    public function getNextImageToPost(): ?Message
    {
        return Message::withImages()
            ->approvedForPosting()
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

