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

        // Get date range for filter
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

        // Get posted images for the selected date range
        $postedImagesFiltered = Message::with('profile')
            ->where('posted_to_page', true)
            ->where('image_generated', true)
            ->whereNotNull('image_path')
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('posted_to_page_at', [$startDate, $endDate]);
            })
            ->latest('posted_to_page_at')
            ->take(12)
            ->get();

        // Calculate stats for filtered period
        $postedStats = [
            'count' => Message::where('posted_to_page', true)
                ->when($startDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('posted_to_page_at', [$startDate, $endDate]);
                })
                ->count(),
            'with_images' => Message::where('posted_to_page', true)
                ->where('image_generated', true)
                ->when($startDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('posted_to_page_at', [$startDate, $endDate]);
                })
                ->count(),
        ];

        return view('livewire.dashboard', [
            'stats' => $stats,
            'postedImagesFiltered' => $postedImagesFiltered,
            'postedStats' => $postedStats,
        ])->layout('components.layouts.app', ['title' => 'Dashboard']);
    }
}
