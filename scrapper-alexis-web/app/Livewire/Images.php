<?php

namespace App\Livewire;

use App\Models\Message;
use App\Services\PostingService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

class Images extends Component
{
    use WithPagination;

    public $selected = [];
    public $previousPage = null;
    public $filter = 'all';
    public $perPage = 25;

    protected $queryString = ['filter', 'perPage'];
    protected $postingService;

    public function boot(PostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    public function mount()
    {
        // Initialize
    }

    public function updatedFilter()
    {
        \Log::info('Images: Filter updated', ['filter' => $this->filter]);
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        \Log::info('Images: Per page updated', ['perPage' => $this->perPage]);
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

    public function approveForAutoPost($messageId)
    {
        \Log::info('Images: Approve for auto-post', ['message_id' => $messageId, 'filter' => $this->filter]);

        $message = Message::find($messageId);

        if (!$message) {
            \Log::error('Images: Message not found', ['message_id' => $messageId]);
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            \Log::warning('Images: Cannot modify posted image', ['message_id' => $messageId]);
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        $result = $this->postingService->approveForAutoPost($messageId);

        if ($result) {
            \Log::info('Images: Image approved for auto-post successfully', ['message_id' => $messageId]);
            session()->flash('success', 'Imagen aprobada para publicación automática');
        } else {
            \Log::error('Images: Failed to approve image for auto-post', ['message_id' => $messageId]);
            session()->flash('error', 'Error al aprobar la imagen');
        }
    }

    public function approveForManualPost($messageId)
    {
        \Log::info('Images: Approve for manual post', ['message_id' => $messageId, 'filter' => $this->filter]);

        $message = Message::find($messageId);

        if (!$message) {
            \Log::error('Images: Message not found', ['message_id' => $messageId]);
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            \Log::warning('Images: Cannot modify posted image', ['message_id' => $messageId]);
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        $result = $this->postingService->approveForManualPost($messageId);

        if ($result) {
            \Log::info('Images: Image approved for manual post successfully', ['message_id' => $messageId]);
            session()->flash('success', 'Imagen aprobada para publicación manual');
        } else {
            \Log::error('Images: Failed to approve image for manual post', ['message_id' => $messageId]);
            session()->flash('error', 'Error al aprobar la imagen');
        }
    }

    public function postImageNow($messageId)
    {
        \Log::info('Images: Manual post now triggered', ['message_id' => $messageId, 'filter' => $this->filter]);

        $message = Message::find($messageId);

        if (!$message) {
            \Log::error('Images: Message not found', ['message_id' => $messageId]);
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Validate image is approved for posting
        if (!$message->approved_for_posting) {
            \Log::warning('Images: Image not approved for posting', ['message_id' => $messageId]);
            session()->flash('error', 'La imagen debe estar aprobada antes de publicar');
            return;
        }

        // Prevent posting if already posted
        if ($message->posted_to_page) {
            \Log::warning('Images: Image already posted', ['message_id' => $messageId]);
            session()->flash('error', 'Esta imagen ya ha sido publicada');
            return;
        }

        \Log::info('Images: Executing manual post for image', ['message_id' => $messageId]);

        // Execute posting script with specific image ID - dynamic path from config
        $pythonPath = config('scraper.python_path');
        $scriptPath = $pythonPath . '/scrapper-alexis';
        $timestamp = date('YmdHis');
        $logFile = $pythonPath . '/' . config('scraper.logs_dir') . "/manual_post_image_{$messageId}_{$timestamp}.log";

        // Feature: Run page poster with MANUAL_RUN=1 and IMAGE_ID={message_id}
        $command = sprintf(
            'cd %s && sudo -u root /bin/bash -c "export MANUAL_RUN=1 && export IMAGE_ID=%d && source venv/bin/activate && xvfb-run -a python3 facebook_page_poster.py" > %s 2>&1 &',
            escapeshellarg($scriptPath),
            $messageId,
            escapeshellarg($logFile)
        );

        \Log::info('Images: Executing manual post command', [
            'message_id' => $messageId,
            'command' => $command,
            'log_file' => $logFile
        ]);

        exec($command, $output, $returnVar);

        // Give script moment to start
        usleep(500000); // 0.5 seconds

        if ($returnVar === 0 || file_exists($logFile)) {
            \Log::info('Images: Manual post script started successfully', [
                'message_id' => $messageId,
                'log_file' => basename($logFile)
            ]);
            session()->flash('success', "Publicación iniciada. Revisa el log: " . basename($logFile));
        } else {
            \Log::error('Images: Failed to start manual post script', [
                'message_id' => $messageId,
                'return_var' => $returnVar,
                'output' => $output
            ]);
            session()->flash('error', 'Error al iniciar la publicación. Revisa los logs.');
        }
    }

    public function rejectImage($messageId)
    {
        \Log::info('Images: Reject image', ['message_id' => $messageId, 'filter' => $this->filter]);

        $message = Message::find($messageId);

        if (!$message) {
            \Log::error('Images: Image not found', ['message_id' => $messageId]);
            session()->flash('error', 'Imagen no encontrada');
            return;
        }

        // Prevent changes if already posted
        if ($message->posted_to_page) {
            \Log::warning('Images: Cannot modify posted image', ['message_id' => $messageId]);
            session()->flash('error', 'No se puede modificar una imagen ya publicada');
            return;
        }

        $result = $this->postingService->rejectImage($messageId);

        if ($result) {
            \Log::info('Images: Image rejected successfully', ['message_id' => $messageId]);
            session()->flash('success', 'Imagen rechazada correctamente');
        } else {
            \Log::error('Images: Failed to reject image', ['message_id' => $messageId]);
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

    public function approveSelectedForAuto()
    {
        \Log::info('Images: Approve selected for auto-post', ['count' => count($this->selected), 'filter' => $this->filter]);

        if (empty($this->selected)) {
            session()->flash('error', 'No hay imágenes seleccionadas');
            return;
        }

        $count = 0;
        foreach ($this->selected as $messageId) {
            if ($this->postingService->approveForAutoPost($messageId)) {
                $count++;
            }
        }

        \Log::info('Images: Bulk approve for auto-post completed', ['approved_count' => $count]);

        $this->selected = [];
        session()->flash('success', "$count imágenes aprobadas para publicación automática");
    }

    public function approveSelectedForManual()
    {
        \Log::info('Images: Approve selected for manual post', ['count' => count($this->selected), 'filter' => $this->filter]);

        if (empty($this->selected)) {
            session()->flash('error', 'No hay imágenes seleccionadas');
            return;
        }

        $count = 0;
        foreach ($this->selected as $messageId) {
            if ($this->postingService->approveForManualPost($messageId)) {
                $count++;
            }
        }

        \Log::info('Images: Bulk approve for manual post completed', ['approved_count' => $count]);

        $this->selected = [];
        session()->flash('success', "$count imágenes aprobadas para publicación manual");
    }

    public function rejectSelected()
    {
        \Log::info('Images: Reject selected', ['count' => count($this->selected), 'filter' => $this->filter]);

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

        \Log::info('Images: Bulk reject completed', ['rejected_count' => $count]);

        $this->selected = [];
        session()->flash('success', "$count imágenes rechazadas");
    }

    public function downloadSelected()
    {
        if (empty($this->selected)) {
            session()->flash('error', 'No hay imágenes seleccionadas');
            return;
        }

        // Store selected IDs before clearing
        $selectedIds = $this->selected;

        // Mark images as downloaded with timestamp
        Message::whereIn('id', $selectedIds)->update([
            'downloaded' => true,
            'downloaded_at' => now()
        ]);
        \Log::info('Images: Marked images as downloaded', ['ids' => $selectedIds, 'downloaded_at' => now()]);

        // Clear selections immediately to prevent state transfer
        $this->selected = [];

        $zipPath = downloadImagesAsZip($selectedIds);

        if ($zipPath) {
            $zipFileName = basename($zipPath);
            session()->flash('success', 'Descarga iniciada');

            // Dispatch event with download URL to trigger browser download via JavaScript
            $downloadUrl = route('images.download', ['zipFileName' => $zipFileName]);
            $this->dispatch('download-ready', url: $downloadUrl);
        } else {
            session()->flash('error', 'Error al crear archivo zip');
            $this->dispatch('download-completed');
        }
    }

    public function downloadImage($messageId)
    {
        $message = Message::find($messageId);

        \Log::info('Bugfix: Download image attempt', [
            'message_id' => $messageId,
            'image_path' => $message->image_path ?? 'null',
            'image_full_path' => $message->image_full_path ?? 'null',
            'file_exists' => $message->image_full_path ? file_exists($message->image_full_path) : false
        ]);

        if ($message && $message->image_full_path && file_exists($message->image_full_path)) {
            // Mark image as downloaded with timestamp
            $message->update([
                'downloaded' => true,
                'downloaded_at' => now()
            ]);
            \Log::info('Images: Marked image as downloaded', ['id' => $messageId, 'downloaded_at' => now()]);

            return response()->download($message->image_full_path);
        }

        \Log::error('Bugfix: Image file not found', [
            'message_id' => $messageId,
            'image_full_path' => $message->image_full_path ?? 'null'
        ]);
        session()->flash('error', 'Archivo de imagen no encontrado');
    }

    public function render()
    {
        \Log::info('Images: Rendering', ['filter' => $this->filter, 'perPage' => $this->perPage]);

        // Build query based on filter
        $query = Message::withImages()->validWordCount();

        switch ($this->filter) {
            case 'approved_auto':
                $query->approvedForPosting()->autoPostEnabled()->notPostedToPage();
                break;
            case 'approved_manual':
                $query->approvedForPosting()->manualPostOnly()->notPostedToPage();
                break;
            case 'posted':
                $query->postedToPage();
                break;
            case 'all':
                // No filter
                break;
        }

        // Sort by posted_to_page_at for posted items (DESC for reverse chronological), otherwise by ID
        if ($this->filter === 'posted') {
            \Log::info('Images: Sorting posted by posted_to_page_at DESC (reverse chronological)');
            $messages = $query->orderByDesc('posted_to_page_at')->paginate($this->perPage);
        } else {
            \Log::info('Images: Sorting by id DESC');
            $messages = $query->latest('id')->paginate($this->perPage);
        }

        \Log::info('Images: Query results', ['total' => $messages->total(), 'current_page' => $messages->currentPage()]);

        // Calculate stats
        $stats = [
            'pending' => Message::withImages()->validWordCount()->pendingApproval()->notPostedToPage()->count(),
            'approved_auto' => Message::withImages()->validWordCount()->approvedForPosting()->autoPostEnabled()->notPostedToPage()->count(),
            'approved_manual' => Message::withImages()->validWordCount()->approvedForPosting()->manualPostOnly()->notPostedToPage()->count(),
            'posted' => Message::withImages()->validWordCount()->postedToPage()->count(),
        ];

        \Log::info('Images: Stats calculated', $stats);

        return view('livewire.images', [
            'stats' => $stats,
            'messages' => $messages,
        ])->layout('components.layouts.app', ['title' => 'Imágenes']);
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
