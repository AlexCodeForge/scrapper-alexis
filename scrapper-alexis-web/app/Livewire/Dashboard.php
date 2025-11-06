<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Profile;
use App\Services\PostingService;
use Livewire\Component;

class Dashboard extends Component
{
    public $dateFilter = 'today';
    public $customStartDate = null;
    public $customEndDate = null;

    protected $queryString = ['dateFilter'];

    public function updatedDateFilter()
    {
        \Log::info('Dashboard: Date filter updated', ['filter' => $this->dateFilter]);
    }

    public function runScript($script)
    {
        $result = runScraperScript($script);

        if ($result['success']) {
            session()->flash('success', $result['message']);
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function postToPage()
    {
        \Log::info('Dashboard: Manual Facebook page post triggered');

        $result = runScraperScript('page_poster');

        if ($result['success']) {
            session()->flash('success', 'Publicación en página de Facebook iniciada correctamente');
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function generateImages()
    {
        \Log::info('Dashboard: Manual image generation triggered');

        $result = runScraperScript('image_generator');

        if ($result['success']) {
            session()->flash('success', 'Generación de imágenes iniciada correctamente');
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

        // BUGFIX: Timezone-aware date filtering
        // Dates are stored in UTC, so we need to convert filter dates to UTC for comparison
        $startDate = null;
        $endDate = now();

        switch ($this->dateFilter) {
            case 'today':
                $startDate = now()->startOfDay();
                break;
            case 'week':
                $startDate = now()->subDays(7)->startOfDay();
                break;
            case 'month':
                $startDate = now()->subDays(30)->startOfDay();
                break;
            case 'custom':
                $startDate = $this->customStartDate ? now()->parse($this->customStartDate) : now()->subDays(30);
                $endDate = $this->customEndDate ? now()->parse($this->customEndDate) : now();
                break;
        }

        // Convert filter dates to UTC for database comparison
        // Database stores dates in UTC, but we filter in app timezone
        $startDateUTC = $startDate ? $startDate->copy()->timezone('UTC') : null;
        $endDateUTC = $endDate->copy()->timezone('UTC');

        // Get latest 5 posted messages for the selected date range
        $messagesQuery = Message::where('posted_to_page', true);

        if ($startDateUTC) {
            $messagesQuery->whereRaw('posted_to_page_at >= ?', [$startDateUTC->toDateTimeString()])
                         ->whereRaw('posted_to_page_at <= ?', [$endDateUTC->toDateTimeString()]);
        }

        $postedMessagesFiltered = $messagesQuery
            ->latest('posted_to_page_at')
            ->limit(5)
            ->get();

        // Calculate stats for filtered period
        $statsQuery = Message::where('posted_to_page', true);

        if ($startDateUTC) {
            $statsQuery->whereRaw('posted_to_page_at >= ?', [$startDateUTC->toDateTimeString()])
                      ->whereRaw('posted_to_page_at <= ?', [$endDateUTC->toDateTimeString()]);
        }

        $postedStats = [
            'count' => $statsQuery->count(),
            'with_images' => (clone $statsQuery)->where('image_generated', true)->count(),
        ];

        \Log::info('Dashboard: Rendering with posted messages', [
            'dateFilter' => $this->dateFilter,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'postedCount' => $postedStats['count'],
            'messagesInView' => $postedMessagesFiltered->count()
        ]);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'postedMessagesFiltered' => $postedMessagesFiltered,
            'postedStats' => $postedStats,
        ])->layout('components.layouts.app', ['title' => 'Dashboard']);
    }
}
