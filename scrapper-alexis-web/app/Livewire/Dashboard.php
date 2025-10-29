<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Profile;
use App\Services\PostingService;
use Livewire\Component;

class Dashboard extends Component
{
    public function runScript($script)
    {
        $result = runScraperScript($script);

        if ($result['success']) {
            session()->flash('success', $result['message']);
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function render()
    {
        $postingService = app(PostingService::class);
        $pageStats = $postingService->getPageStats();

        $stats = [
            'total_messages' => Message::count(),
            // Only count actually posted messages (exclude quality-filter skipped ones)
            'posted_to_twitter' => Message::where('posted_to_twitter', true)
                ->where('post_url', '!=', 'SKIPPED_QUALITY_FILTER')
                ->whereNotNull('post_url')
                ->count(),
            'images_generated' => Message::where('image_generated', true)->count(),
            'active_profiles' => Profile::where('is_active', true)->count(),
            'approved_for_page' => $pageStats['approved'],
            'posted_to_page' => $pageStats['posted'],
        ];

        // Load all messages for instant tab switching with Alpine
        // Apply word count filter to match scraper's deduplicator logic (>4 words)
        $allMessages = Message::with('profile')
            ->validWordCount()
            ->latest('scraped_at')
            ->take(5)
            ->get();

        $postedMessages = Message::with('profile')
            ->validWordCount()
            ->where('posted_to_twitter', true)
            ->where(function($query) {
                $query->whereNull('posted_at')
                      ->orWhereRaw('datetime(posted_at) <= datetime(?)', [now()]);
            })
            ->latest('id')
            ->take(5)
            ->get();

        // Get scheduled posts (future posts) ordered by when they'll be published
        // Use datetime() for proper comparison in SQLite to avoid string comparison issues
        $scheduledMessages = Message::with('profile')
            ->validWordCount()
            ->where('posted_to_twitter', true)
            ->whereNotNull('posted_at')
            ->whereRaw('datetime(posted_at) > datetime(?)', [now()])
            ->orderBy('posted_at', 'asc')
            ->get();

        // Get truly pending messages (not posted yet)
        // Order by scraped_at ASC (oldest first) to match Twitter poster's logic
        $unpostedMessages = Message::with('profile')
            ->validWordCount()
            ->where('posted_to_twitter', false)
            ->orderBy('scraped_at', 'asc')
            ->get();

        // Combine: scheduled first, then pending, limit to 5 total
        $pendingMessages = $scheduledMessages->concat($unpostedMessages)->take(5);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'allMessages' => $allMessages,
            'postedMessages' => $postedMessages,
            'pendingMessages' => $pendingMessages,
        ])->layout('components.layouts.app', ['title' => 'Dashboard']);
    }
}
