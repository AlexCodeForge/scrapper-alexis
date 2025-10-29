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
        $message = Message::find($messageId);

        if (!$message) {
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        $result = $this->postingService->approveImage($messageId);

        if ($result) {
            session()->flash('success', 'Imagen aprobada correctamente');
        } else {
            session()->flash('error', 'Error al aprobar la imagen');
        }
    }

    public function rejectImage($messageId)
    {
        $message = Message::find($messageId);

        if (!$message) {
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        // Direct update with logging for debugging
        \Log::info('Bugfix: Before reject', ['id' => $messageId, 'approved_at_before' => $message->approved_at]);

        $result = $message->update([
            'approved_for_posting' => false,
            'approved_at' => now(),
        ]);

        $message->refresh();
        \Log::info('Bugfix: After reject', ['id' => $messageId, 'approved_at_after' => $message->approved_at, 'result' => $result]);

        if ($result) {
            session()->flash('success', 'Imagen rechazada');
        } else {
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

        $this->selected = [];
        session()->flash('success', "$count imágenes aprobadas correctamente");

        // Force full page reload to refresh pagination
        $this->js('window.location.href = window.location.href');
    }

    public function rejectSelected()
    {
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

        $this->selected = [];
        session()->flash('success', "$count imágenes rechazadas");

        // Force full page reload to refresh pagination
        $this->js('window.location.href = window.location.href');
    }

    public function render()
    {
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

        // Calculate stats
        $stats = [
            'pending' => Message::withImages()->validWordCount()->pendingApproval()->notPostedToPage()->count(),
            'approved' => Message::withImages()->validWordCount()->approvedForPosting()->notPostedToPage()->count(),
            'rejected' => Message::withImages()->validWordCount()->where('approved_for_posting', false)->whereNotNull('approved_at')->count(),
            'posted' => Message::withImages()->validWordCount()->postedToPage()->count(),
        ];

        return view('livewire.posting-approval', [
            'stats' => $stats,
            'messages' => $messages,
        ])->layout('components.layouts.app', ['title' => 'Aprobación de Publicaciones']);
    }
}

