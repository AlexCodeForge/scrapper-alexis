<?php

namespace App\Livewire;

use App\Models\Message;
use App\Services\PostingService;
use Livewire\Component;
use Livewire\WithPagination;

class PostingApproval extends Component
{
    use WithPagination;

    public $selected = [];
    public $approvalFilter = 'pending';
    public $perPage = 10;

    protected $queryString = ['approvalFilter', 'perPage'];
    protected $postingService;

    public function boot(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    public function mount()
    {
        // Initialize
    }

    public function updatedApprovalFilter()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function updating($property, $value)
    {
        // Clear selections when page changes
        if ($property === 'paginators.page') {
            $this->selected = [];
            $this->dispatch('pagination-changed');
        }
    }

    public function approveImage($messageId)
    {
        \Log::info('PostingApproval: Approve image', ['message_id' => $messageId, 'filter' => $this->approvalFilter]);

        $message = Message::find($messageId);

        if (!$message) {
            \Log::error('PostingApproval: Image not found', ['message_id' => $messageId]);
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            \Log::warning('PostingApproval: Cannot modify posted image', ['message_id' => $messageId]);
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        $result = $this->postingService->approveImage($messageId);

        if ($result) {
            \Log::info('PostingApproval: Image approved successfully', ['message_id' => $messageId]);
            session()->flash('success', 'Imagen aprobada correctamente');
            // Livewire will auto-refresh after this method completes
        } else {
            \Log::error('PostingApproval: Failed to approve image', ['message_id' => $messageId]);
            session()->flash('error', 'Error al aprobar la imagen');
        }
    }

    public function rejectImage($messageId)
    {
        \Log::info('PostingApproval: Reject image', ['message_id' => $messageId, 'filter' => $this->approvalFilter]);

        $message = Message::find($messageId);

        if (!$message) {
            \Log::error('PostingApproval: Image not found', ['message_id' => $messageId]);
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            \Log::warning('PostingApproval: Cannot modify posted image', ['message_id' => $messageId]);
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        $result = $this->postingService->rejectImage($messageId);

        if ($result) {
            \Log::info('PostingApproval: Image rejected successfully', ['message_id' => $messageId]);
            session()->flash('success', 'Imagen rechazada correctamente');
            // Livewire will auto-refresh after this method completes
        } else {
            \Log::error('PostingApproval: Failed to reject image', ['message_id' => $messageId]);
            session()->flash('error', 'Error al rechazar la imagen');
        }
    }

    public function deleteImage($messageId)
    {
        $message = Message::find($messageId);

        if (!$message) {
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent deletion of approved but not posted images
        if ($message->approved_for_posting && !$message->posted_to_page) {
            session()->flash('error', 'No se puede eliminar una imagen aprobada que aún no ha sido publicada');
            return;
        }

        $message->delete();
        session()->flash('success', 'Imagen eliminada correctamente');
        // Bugfix: Refresh component to update UI without full page reload
        $this->js('$wire.$refresh()');
    }

    public function deleteSelected()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No hay imágenes seleccionadas');
            return;
        }

        // Check if any selected images are approved but not posted
        $protectedImages = Message::whereIn('id', $this->selected)
            ->where('approved_for_posting', true)
            ->where('posted_to_page', false)
            ->count();

        if ($protectedImages > 0) {
            session()->flash('error', "No se pueden eliminar {$protectedImages} imagen(es) aprobada(s) que aún no ha(n) sido publicada(s)");
            return;
        }

        Message::whereIn('id', $this->selected)->delete();
        $count = count($this->selected);
        $this->selected = [];
        session()->flash('success', "$count imágenes eliminadas correctamente");
    }

    public function toggleSelection($messageId)
    {
        if (in_array($messageId, $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$messageId]));
        } else {
            $this->selected[] = $messageId;
        }
    }

    public function approveSelected()
    {
        \Log::info('PostingApproval: Approve selected', ['count' => count($this->selected), 'filter' => $this->approvalFilter]);

        if (empty($this->selected)) {
            session()->flash('error', 'No hay imágenes seleccionadas');
            return;
        }

        $count = 0;
        foreach ($this->selected as $messageId) {
            if ($this->postingService->approveImage($messageId)) {
                $count++;
            }
        }

        \Log::info('PostingApproval: Bulk approve completed', ['approved_count' => $count]);

        $this->selected = [];
        session()->flash('success', "$count imágenes aprobadas correctamente");

        // Livewire will auto-refresh after this method completes
    }

    public function rejectSelected()
    {
        \Log::info('PostingApproval: Reject selected', ['count' => count($this->selected), 'filter' => $this->approvalFilter]);

        if (empty($this->selected)) {
            session()->flash('error', 'No hay imágenes seleccionadas');
            return;
        }

        $count = 0;
        foreach ($this->selected as $messageId) {
            if ($this->postingService->rejectImage($messageId)) {
                $count++;
            }
        }

        \Log::info('PostingApproval: Bulk reject completed', ['rejected_count' => $count]);

        $this->selected = [];
        session()->flash('success', "$count imágenes rechazadas");

        // Livewire will auto-refresh after this method completes
    }

    public function render()
    {
        \Log::info('PostingApproval: Rendering', ['filter' => $this->approvalFilter, 'perPage' => $this->perPage]);

        // Build query based on filter
        $query = Message::withImages()->validWordCount();

        switch ($this->approvalFilter) {
            case 'pending':
                $query->pendingApproval()->notPostedToPage();
                break;
            case 'approved':
                $query->approvedForPosting()->notPostedToPage();
                break;
            case 'rejected':
                $query->where('approved_for_posting', false)->whereNotNull('approved_at');
                break;
            case 'posted':
                $query->postedToPage();
                break;
            case 'all':
                // No filter
                break;
        }

        $messages = $query->latest('scraped_at')->paginate($this->perPage);

        \Log::info('PostingApproval: Query results', ['total' => $messages->total(), 'current_page' => $messages->currentPage()]);

        // Calculate stats
        $stats = [
            'pending' => Message::withImages()->validWordCount()->pendingApproval()->notPostedToPage()->count(),
            'approved' => Message::withImages()->validWordCount()->approvedForPosting()->notPostedToPage()->count(),
            'rejected' => Message::withImages()->validWordCount()->where('approved_for_posting', false)->whereNotNull('approved_at')->count(),
            'posted' => Message::withImages()->validWordCount()->postedToPage()->count(),
        ];

        \Log::info('PostingApproval: Stats calculated', $stats);

        return view('livewire.posting-approval', [
            'stats' => $stats,
            'messages' => $messages,
        ])->layout('components.layouts.app', ['title' => 'Aprobación de Publicaciones']);
    }
}

