<?php

namespace App\Livewire;

use App\Models\Message;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

class ImageGallery extends Component
{
    use WithPagination;

    public $selected = [];
    public $previousPage = null;
    public $downloadFilter = 'not_downloaded';
    public $perPage = 10;

    protected $queryString = ['downloadFilter', 'perPage'];

    public function toggleSelection($messageId)
    {
        \Log::info('ImageGallery: Toggle selection', ['message_id' => $messageId]);

        if (in_array($messageId, $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$messageId]));
        } else {
            $this->selected[] = $messageId;
        }
    }

    public function updatedDownloadFilter()
    {
        \Log::info('ImageGallery: Download filter updated', ['downloadFilter' => $this->downloadFilter]);
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        \Log::info('ImageGallery: Per page updated', ['perPage' => $this->perPage]);
        $this->resetPage();
    }

    public function updating($property, $value)
    {
        \Log::info('ImageGallery: Updating property', ['property' => $property, 'value' => $value]);

        // Clear selections when page changes
        if ($property === 'paginators.page') {
            \Log::info('ImageGallery: Page changed - clearing selections');
            $this->selected = [];
            $this->dispatch('pagination-changed');
        }
    }

    public function updated($property, $value)
    {
        \Log::info('ImageGallery: Updated property', ['property' => $property, 'value' => $value]);

        // Dispatch event after page change completes
        if ($property === 'paginators.page') {
            \Log::info('ImageGallery: Page updated - dispatching event');
            $this->dispatch('pagination-changed');
        }
    }

    public function downloadSelected()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No images selected');
            return;
        }

        // Store selected IDs before clearing
        $selectedIds = $this->selected;

        // Mark images as downloaded with timestamp
        Message::whereIn('id', $selectedIds)->update([
            'downloaded' => true,
            'downloaded_at' => now()
        ]);
        \Log::info('ImageGallery: Marked images as downloaded', ['ids' => $selectedIds, 'downloaded_at' => now()]);

        // Clear selections immediately to prevent state transfer
        $this->selected = [];

        $zipPath = downloadImagesAsZip($selectedIds);

        if ($zipPath) {
            $zipFileName = basename($zipPath);
            session()->flash('success', 'Download started');

            // Dispatch event with download URL to trigger browser download via JavaScript
            $downloadUrl = route('images.download', ['zipFileName' => $zipFileName]);
            $this->dispatch('download-ready', url: $downloadUrl);
        } else {
            session()->flash('error', 'Failed to create zip file');
            $this->dispatch('download-completed');
        }
    }

    public function deleteSelected()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No images selected');
            return;
        }

        $count = deleteImages($this->selected);
        $this->selected = [];

        session()->flash('success', "$count images deleted successfully");
    }

    public function deleteImage($messageId)
    {
        $count = deleteImages([$messageId]);

        if ($count > 0) {
            session()->flash('success', 'Image deleted successfully');
        } else {
            session()->flash('error', 'Failed to delete image');
        }
    }

    public function downloadImage($messageId)
    {
        $message = Message::find($messageId);

        if ($message && $message->image_full_path && file_exists($message->image_full_path)) {
            // Mark image as downloaded with timestamp
            $message->update([
                'downloaded' => true,
                'downloaded_at' => now()
            ]);
            \Log::info('ImageGallery: Marked image as downloaded', ['id' => $messageId, 'downloaded_at' => now()]);

            return response()->download($message->image_full_path);
        }

        session()->flash('error', 'Image file not found');
    }

    public function render()
    {
        \Log::info('ImageGallery: Rendering component - START', [
            'selected_count' => count($this->selected),
            'previous_page' => $this->previousPage
        ]);

        // Stats for widgets
        $stats = [
            'total_images' => Message::where('image_generated', true)->count(),
            'images_posted' => Message::where('image_generated', true)
                ->where('posted_to_twitter', true)
                ->count(),
            'images_downloaded' => Message::where('image_generated', true)
                ->where('downloaded', true)
                ->count(),
            'total_size' => $this->calculateTotalImageSize(),
        ];

        $messages = Message::withImages()
            ->when($this->downloadFilter === 'not_downloaded', function ($query) {
                $query->notDownloaded();
            })
            ->when($this->downloadFilter === 'downloaded', function ($query) {
                $query->downloaded();
            })
            ->latest('posted_at')
            ->paginate($this->perPage);

        $currentPage = $messages->currentPage();

        \Log::info('ImageGallery: Rendering component - AFTER PAGINATE', [
            'current_page' => $currentPage,
            'previous_page' => $this->previousPage,
            'selected_count' => count($this->selected)
        ]);

        // Clear selections if page changed
        if ($this->previousPage !== null && $this->previousPage != $currentPage) {
            \Log::info('ImageGallery: Page changed, clearing selections', [
                'from' => $this->previousPage,
                'to' => $currentPage
            ]);
            $this->selected = [];
            $this->dispatch('pagination-changed');
        }

        $this->previousPage = $currentPage;

        \Log::info('ImageGallery: Stats calculated', $stats);

        return view('livewire.image-gallery', [
            'messages' => $messages,
            'stats' => $stats,
        ])->layout('components.layouts.app', ['title' => 'Image Gallery']);
    }

    private function calculateTotalImageSize()
    {
        $messages = Message::where('image_generated', true)->get();
        $totalSize = 0;

        foreach ($messages as $message) {
            if ($message->image_full_path && file_exists($message->image_full_path)) {
                $totalSize += filesize($message->image_full_path);
            }
        }

        // Convert to MB
        return round($totalSize / 1024 / 1024, 2);
    }
}
