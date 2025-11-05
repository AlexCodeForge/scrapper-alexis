<?php

namespace App\Livewire;

use App\Models\Message;
use App\Services\PostingService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ScrapedMessages extends Component
{
    use WithPagination;

    public $perPage = 25;
    public $selected = [];
    public $selectAll = false;
    
    #[Url(keep: true)]
    public $filter = 'all';

    protected $queryString = ['perPage', 'filter'];

    public function updatedPerPage()
    {
        \Log::info('ScrapedMessages: Per page updated', ['perPage' => $this->perPage]);
        $this->resetPage();
    }

    public function updatedFilter()
    {
        \Log::info('ScrapedMessages: Filter updated', ['filter' => $this->filter]);
        $this->resetPage();
    }

    /**
     * Approve a message for AUTO posting (image generation + auto-post)
     */
    public function approveMessage(int $messageId)
    {
        try {
            $message = Message::find($messageId);
            
            if (!$message) {
                session()->flash('error', 'Mensaje no encontrado');
                return;
            }

            $message->update([
                'approved_for_posting' => true,
                'approved_at' => now(),
                'auto_post_enabled' => true,
                'approval_type' => 'auto',
            ]);

            session()->flash('success', 'Mensaje aprobado para auto-publicación. Se generará la imagen y se publicará automáticamente.');
            
            \Log::info('ScrapedMessages: Message approved for auto-post', [
                'message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error al aprobar el mensaje: ' . $e->getMessage());
            \Log::error('ScrapedMessages: Error approving message', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Approve a message for MANUAL posting (image generation but NO auto-post)
     */
    public function approveForManual(int $messageId)
    {
        try {
            $message = Message::find($messageId);
            
            if (!$message) {
                session()->flash('error', 'Mensaje no encontrado');
                return;
            }

            $message->update([
                'approved_for_posting' => true,
                'approved_at' => now(),
                'auto_post_enabled' => false,
                'approval_type' => 'manual',
            ]);

            session()->flash('success', 'Mensaje aprobado para publicación manual. Se generará la imagen pero NO se publicará automáticamente.');
            
            \Log::info('ScrapedMessages: Message approved for manual post', [
                'message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error al aprobar el mensaje: ' . $e->getMessage());
            \Log::error('ScrapedMessages: Error approving message for manual', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Reject a message (marks as not approved)
     */
    public function rejectMessage(int $messageId)
    {
        try {
            $message = Message::find($messageId);
            
            if (!$message) {
                session()->flash('error', 'Mensaje no encontrado');
                return;
            }

            $message->update([
                'approved_for_posting' => false,
                'approved_at' => now(),
            ]);

            session()->flash('success', 'Mensaje rechazado.');
            
            \Log::info('ScrapedMessages: Message rejected', [
                'message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            session()->flash('error', 'Error al rechazar el mensaje: ' . $e->getMessage());
            \Log::error('ScrapedMessages: Error rejecting message', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk approve selected messages for auto-post
     */
    public function bulkApprove()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No hay mensajes seleccionados');
            return;
        }

        Message::whereIn('id', $this->selected)->update([
            'approved_for_posting' => true,
            'approved_at' => now(),
            'auto_post_enabled' => true,
            'approval_type' => 'auto',
        ]);

        session()->flash('success', count($this->selected) . ' mensajes aprobados para auto-publicación');
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * Bulk approve selected messages for manual post
     */
    public function bulkApproveManual()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No hay mensajes seleccionados');
            return;
        }

        Message::whereIn('id', $this->selected)->update([
            'approved_for_posting' => true,
            'approved_at' => now(),
            'auto_post_enabled' => false,
            'approval_type' => 'manual',
        ]);

        session()->flash('success', count($this->selected) . ' mensajes aprobados para publicación manual');
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * Bulk reject selected messages
     */
    public function bulkReject()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No hay mensajes seleccionados');
            return;
        }

        Message::whereIn('id', $this->selected)->update([
            'approved_for_posting' => false,
            'approved_at' => now(),
        ]);

        session()->flash('success', count($this->selected) . ' mensajes rechazados');
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * Toggle select all
     */
    public function updatedSelectAll($value)
    {
        if ($value) {
            // Select all messages on current page
            $this->selected = Message::where(function ($query) {
                    $query->where('image_generated', false)
                          ->orWhereNull('image_generated');
                })
                ->orderBy('scraped_at', 'desc')
                ->limit($this->perPage)
                ->pluck('id')
                ->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function render()
    {
        \Log::info('ScrapedMessages: Rendering', ['perPage' => $this->perPage, 'filter' => $this->filter]);

        // Build base query: Show ALL messages without images
        // Once image is generated, message disappears from this page
        $query = Message::with('profile')
            ->where(function ($q) {
                $q->where('image_generated', false)
                  ->orWhereNull('image_generated');
            });

        // Apply filter based on approval status
        switch ($this->filter) {
            case 'pending':
                // Pending: No approval decision made yet (approved_for_posting is NULL)
                $query->whereNull('approved_for_posting');
                break;
            case 'approved_auto':
                // Approved for auto-post
                $query->where('approved_for_posting', true)
                      ->where('auto_post_enabled', true);
                break;
            case 'approved_manual':
                // Approved for manual post
                $query->where('approved_for_posting', true)
                      ->where('auto_post_enabled', false);
                break;
            case 'rejected':
                // Rejected: approved_for_posting is false AND approved_at is not null
                $query->where('approved_for_posting', false)
                      ->whereNotNull('approved_at');
                break;
            case 'all':
            default:
                // No additional filter - show all messages without images
                break;
        }

        $messages = $query->orderBy('scraped_at', 'desc')
            ->paginate($this->perPage);

        // Calculate statistics for filter badges
        $stats = [
            'pending' => Message::where(function ($q) {
                    $q->where('image_generated', false)->orWhereNull('image_generated');
                })
                ->whereNull('approved_for_posting')
                ->count(),
            'approved_auto' => Message::where(function ($q) {
                    $q->where('image_generated', false)->orWhereNull('image_generated');
                })
                ->where('approved_for_posting', true)
                ->where('auto_post_enabled', true)
                ->count(),
            'approved_manual' => Message::where(function ($q) {
                    $q->where('image_generated', false)->orWhereNull('image_generated');
                })
                ->where('approved_for_posting', true)
                ->where('auto_post_enabled', false)
                ->count(),
            'rejected' => Message::where(function ($q) {
                    $q->where('image_generated', false)->orWhereNull('image_generated');
                })
                ->where('approved_for_posting', false)
                ->whereNotNull('approved_at')
                ->count(),
            'all' => Message::where(function ($q) {
                    $q->where('image_generated', false)->orWhereNull('image_generated');
                })
                ->count(),
        ];

        \Log::info('ScrapedMessages: Query executed', [
            'total_results' => $messages->total(),
            'current_page' => $messages->currentPage(),
            'filter' => $this->filter,
            'stats' => $stats
        ]);

        return view('livewire.scraped-messages', [
            'messages' => $messages,
            'stats' => $stats,
        ])->layout('components.layouts.app', ['title' => 'Mensajes']);
    }
}

