<?php

namespace App\Livewire;

use App\Models\Message;
use Livewire\Component;
use Livewire\WithPagination;

class ScrapedMessages extends Component
{
    use WithPagination;

    public $perPage = 25;

    protected $queryString = ['perPage'];

    public function updatedPerPage()
    {
        \Log::info('ScrapedMessages: Per page updated', ['perPage' => $this->perPage]);
        $this->resetPage();
    }

    public function render()
    {
        \Log::info('ScrapedMessages: Rendering', ['perPage' => $this->perPage]);

        // Build query - simple, no filters
        $messages = Message::with('profile')
            ->orderBy('scraped_at', 'desc')
            ->paginate($this->perPage);

        \Log::info('ScrapedMessages: Query executed', [
            'total_results' => $messages->total(),
            'current_page' => $messages->currentPage()
        ]);

        return view('livewire.scraped-messages', [
            'messages' => $messages,
        ])->layout('components.layouts.app', ['title' => 'Mensajes Scrapeados']);
    }
}

