<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
     x-data="{
        showCreateModal: @entangle('showCreateModal'),
        showImageGenerationModal: @entangle('showImageGenerationModal'),
        clipboardPasted: false,
        async pasteFromClipboard() {
            // Check if Clipboard API is available (requires HTTPS or localhost)
            if (!navigator.clipboard || !navigator.clipboard.readText) {
                console.warn('Clipboard API not available (requires HTTPS)');
                // Focus the textarea and show helpful message
                const textarea = document.getElementById('newMessageText');
                if (textarea) {
                    textarea.focus();
                }
                alert('Para pegar desde el portapapeles:\n\n' + 
                      '1. Haz clic en el área de texto\n' +
                      '2. Usa Ctrl+V (Windows/Linux) o Cmd+V (Mac)\n\n' +
                      'Nota: La función automática requiere HTTPS');
                return;
            }
            
            try {
                const text = await navigator.clipboard.readText();
                @this.set('newMessageText', text);
                this.clipboardPasted = true;
                console.log('Clipboard paste:', text.substring(0, 50));
                setTimeout(() => this.clipboardPasted = false, 2000);
            } catch (err) {
                console.error('Failed to read clipboard:', err);
                const textarea = document.getElementById('newMessageText');
                if (textarea) {
                    textarea.focus();
                }
                alert('No se pudo acceder al portapapeles.\n\n' +
                      'Por favor, pega el texto manualmente con Ctrl+V o Cmd+V');
            }
        }
    }">
    <!-- Cache buster: v2.0 -->
    <!-- Flash Messages -->
    @if (session()->has('success'))
        <x-alert class="mb-6">
            <x-lucide-circle-check class="h-4 w-4" />
            <x-alert.title>Éxito</x-alert.title>
            <x-alert.description>{{ session('success') }}</x-alert.description>
        </x-alert>
    @endif

    @if (session()->has('error'))
        <x-alert variant="destructive" class="mb-6">
            <x-lucide-triangle-alert class="h-4 w-4" />
            <x-alert.title>Error</x-alert.title>
            <x-alert.description>{{ session('error') }}</x-alert.description>
        </x-alert>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 stats-grid-3 mb-8">
        <!-- Pending -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between space-x-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-muted-foreground truncate">Pendientes</p>
                        <p class="text-3xl font-bold text-foreground mt-2">{{ number_format($stats['pending']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-clock class="h-10 w-10 text-gray-500" />
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Approved -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between space-x-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-muted-foreground truncate">Aprobados</p>
                        <p class="text-3xl font-bold text-foreground mt-2">{{ number_format($stats['approved_auto'] + $stats['approved_manual']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-check-circle class="h-10 w-10 text-blue-500" />
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Rejected -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between space-x-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-muted-foreground truncate">Rechazados</p>
                        <p class="text-3xl font-bold text-foreground mt-2">{{ number_format($stats['rejected']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-x-circle class="h-10 w-10 text-red-500" />
                    </div>
                </div>
            </x-card.content>
        </x-card>
    </div>

    <!-- Filters and Actions -->
    <x-card class="mb-6">
        <x-card.content class="p-4">
            <div class="flex flex-col filters-container items-center justify-between gap-4">
                <!-- Filters -->
                <div class="flex flex-col filters-inner gap-3 items-center">
                    <!-- Search Input -->
                    <div class="flex gap-2 w-full max-w-md">
                        <x-input
                            type="text"
                            wire:model.live="search"
                            placeholder="Buscar mensajes..."
                            class="flex-1" />
                        @if(!empty($search))
                            <x-button
                                wire:click="$set('search', '')"
                                type="button"
                                variant="outline"
                                size="sm">
                                Limpiar
                            </x-button>
                        @endif
                    </div>

                    <x-select wire:model.live="filter" class="min-w-[200px]">
                        <option value="all">Todos</option>
                        <option value="pending">Pendientes</option>
                        <option value="approved_auto">Auto-Post</option>
                        <option value="approved_manual">Manual</option>
                        <option value="rejected">Rechazados</option>
                    </x-select>

                    <x-select wire:model.live="perPage" class="w-48">
                        <option value="10">10 por página</option>
                        <option value="25">25 por página</option>
                        <option value="50">50 por página</option>
                        <option value="100">100 por página</option>
                    </x-select>

                    <x-button 
                        type="button"
                        @click="showCreateModal = true; console.log('Create modal opened')"
                        class="min-w-[200px]">
                        <x-lucide-plus class="w-4 h-4 mr-2" />
                        Crear Mensaje
                    </x-button>
                </div>

                <!-- Bulk Actions -->
                @if(count($selected) > 0)
                    <div class="flex flex-wrap gap-2">
                        <span class="text-sm text-gray-600 self-center">{{ count($selected) }} seleccionados</span>

                        <button
                            wire:click="bulkApprove"
                            type="button"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors">
                            <x-lucide-zap class="inline-block w-4 h-4 mr-1" />
                            Aprobar
                        </button>

                        <button
                            wire:click="bulkApproveManual"
                            type="button"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                            <x-lucide-hand class="inline-block w-4 h-4 mr-1" />
                            Manual
                        </button>

                        <button
                            wire:click="bulkReject"
                            type="button"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">
                            <x-lucide-x class="inline-block w-4 h-4 mr-1" />
                            Rechazar
                        </button>
                    </div>
                @endif
            </div>
        </x-card.content>
    </x-card>

    <!-- Messages Table -->
    <x-card>
        <x-card.content class="p-0">
            <!-- Loading Skeleton -->
            <div wire:loading.delay.longer wire:target="perPage, gotoPage, nextPage, previousPage" class="p-6">
                <div class="animate-pulse space-y-4">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="flex items-center space-x-4">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <!-- Table -->
            <div wire:loading.class="hidden" wire:target="perPage, gotoPage, nextPage, previousPage" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectAll"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Mensaje
                            </th>
                            <th scope="col" class="max-md:hidden px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col" class="max-md:hidden px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col" class="max-md:hidden px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-y divide-border">
                        @forelse ($messages as $message)
                            <tr class="hover:bg-accent/50 transition-colors">
                                <td class="px-4 py-4 align-top">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selected"
                                        value="{{ $message->id }}"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-start space-x-3">
                                        @if ($message->image_generated)
                                            <div class="flex-shrink-0">
                                                <span class="text-green-500 text-xl">🖼️</span>
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-foreground mb-3">
                                                {{ $message->message_text }}
                                            </p>
                                            
                                            <!-- Mobile: Status and Actions below message -->
                                            <div class="md:hidden space-y-3">
                                                <!-- Status Badge on Mobile -->
                                                <div class="text-sm">
                                                    @if ($message->approved_for_posting === true)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            <x-lucide-check class="w-3 h-3 mr-1" />
                                                            Aprobado
                                                        </span>
                                                    @elseif ($message->approved_for_posting === false)
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            <x-lucide-x class="w-3 h-3 mr-1" />
                                                            Rechazado
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            <x-lucide-clock class="w-3 h-3 mr-1" />
                                                            Pendiente
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                <!-- Action Buttons on Mobile -->
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <button
                                                        wire:click="approveAndGenerateImage({{ $message->id }})"
                                                        type="button"
                                                        wire:loading.attr="disabled"
                                                        wire:target="approveAndGenerateImage({{ $message->id }})"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                        <x-lucide-zap class="w-3 h-3 mr-1" />
                                                        <span wire:loading.remove wire:target="approveAndGenerateImage({{ $message->id }})">Generar</span>
                                                        <span wire:loading wire:target="approveAndGenerateImage({{ $message->id }})">...</span>
                                                    </button>
                                                    <button
                                                        wire:click="approveMessage({{ $message->id }})"
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors">
                                                        Aprobar
                                                    </button>
                                                    <button
                                                        wire:click="approveForManual({{ $message->id }})"
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                                        Manual
                                                    </button>
                                                    <button
                                                        wire:click="rejectMessage({{ $message->id }})"
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">
                                                        Rechazar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-md:hidden px-6 py-4 whitespace-nowrap align-top">
                                    <div class="text-sm text-muted-foreground">
                                        {{ $message->scraped_at ? $message->scraped_at->locale('es')->diffForHumans() : 'N/A' }}
                                    </div>
                                </td>
                                <td class="max-md:hidden px-6 py-4 whitespace-nowrap align-top">
                                    <div class="text-sm">
                                        @if ($message->approved_for_posting === true)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <x-lucide-check class="w-3 h-3 mr-1" />
                                                Aprobado
                                            </span>
                                        @elseif ($message->approved_for_posting === false)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <x-lucide-x class="w-3 h-3 mr-1" />
                                                Rechazado
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <x-lucide-clock class="w-3 h-3 mr-1" />
                                                Pendiente
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="max-md:hidden px-6 py-4 align-top">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <button
                                            wire:click="approveAndGenerateImage({{ $message->id }})"
                                            type="button"
                                            wire:loading.attr="disabled"
                                            wire:target="approveAndGenerateImage({{ $message->id }})"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                            <x-lucide-zap class="w-3 h-3 mr-1" />
                                            <span wire:loading.remove wire:target="approveAndGenerateImage({{ $message->id }})">Generar Ahora</span>
                                            <span wire:loading wire:target="approveAndGenerateImage({{ $message->id }})">Generando...</span>
                                        </button>
                                        <button
                                            wire:click="approveMessage({{ $message->id }})"
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors">
                                            Aprobar
                                        </button>
                                        <button
                                            wire:click="approveForManual({{ $message->id }})"
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                            Manual
                                        </button>
                                        <button
                                            wire:click="rejectMessage({{ $message->id }})"
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">
                                            Rechazar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="md:hidden px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <x-lucide-inbox class="h-12 w-12 text-muted-foreground" />
                                        <h3 class="text-sm font-medium text-foreground">No se encontraron mensajes</h3>
                                        <p class="text-sm text-muted-foreground">
                                            Los mensajes scrapeados aparecerán aquí
                                        </p>
                                    </div>
                                </td>
                                <td colspan="5" class="hidden md:table-cell px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <x-lucide-inbox class="h-12 w-12 text-muted-foreground" />
                                        <h3 class="text-sm font-medium text-foreground">No se encontraron mensajes</h3>
                                        <p class="text-sm text-muted-foreground">
                                            Los mensajes scrapeados aparecerán aquí
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($messages->hasPages())
                <div class="px-6 py-4 border-t border-border">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            @if ($messages->onFirstPage())
                                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-muted-foreground bg-muted border border-input cursor-not-allowed leading-5 rounded-md">
                                    Anterior
                                </span>
                            @else
                                <button type="button" wire:click="previousPage" wire:loading.attr="disabled"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-background border border-input leading-5 rounded-md hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:border-input transition ease-in-out duration-150">
                                    Anterior
                                </button>
                            @endif

                            @if ($messages->hasMorePages())
                                <button type="button" wire:click="nextPage" wire:loading.attr="disabled"
                                        class="ml-3 relative inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-background border border-input leading-5 rounded-md hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:border-input transition ease-in-out duration-150">
                                    Siguiente
                                </button>
                            @else
                                <span class="ml-3 relative inline-flex items-center px-4 py-2 text-sm font-medium text-muted-foreground bg-muted border border-input cursor-not-allowed leading-5 rounded-md">
                                    Siguiente
                                </span>
                            @endif
                        </div>

                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Mostrando
                                    <span class="font-medium text-foreground">{{ $messages->firstItem() ?? 0 }}</span>
                                    a
                                    <span class="font-medium text-foreground">{{ $messages->lastItem() ?? 0 }}</span>
                                    de
                                    <span class="font-medium text-foreground">{{ $messages->total() }}</span>
                                    mensajes
                                </p>
                            </div>
                            <div>
                                {{ $messages->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </x-card.content>
    </x-card>

    <!-- Create Message Modal -->
    <template x-teleport="body">
        <div x-show="showCreateModal && !showImageGenerationModal"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="showCreateModal = false; showImageGenerationModal = false">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-foreground">Crear Nuevo Mensaje</h2>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

            <div class="space-y-4">
                <!-- Textarea for message -->
                <div>
                    <label for="newMessageText" class="block text-sm font-medium text-foreground mb-2">
                        Texto del Mensaje
                    </label>
                    <textarea
                        id="newMessageText"
                        wire:model="newMessageText"
                        rows="6"
                        class="w-full px-3 py-2 border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-input"
                        placeholder="Escribe tu mensaje aquí... (mínimo 5 palabras, máximo 500 caracteres)"></textarea>
                    @error('newMessageText')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Paste from clipboard button -->
                <div class="flex items-center gap-2">
                    <x-button 
                        type="button"
                        variant="outline"
                        @click="pasteFromClipboard()"
                        class="flex items-center gap-2">
                        <x-lucide-clipboard class="w-4 h-4" />
                        Pegar desde Portapapeles
                    </x-button>
                    <span x-show="clipboardPasted" 
                          x-transition
                          class="text-sm text-green-600 flex items-center gap-1">
                        <x-lucide-check class="w-4 h-4" />
                        ¡Pegado!
                    </span>
                </div>

                <!-- Action buttons -->
                <div class="flex flex-wrap gap-3 pt-4 border-t border-border">
                    <x-button 
                        type="button"
                        @click="showImageGenerationModal = true; console.log('Image generation modal opened')"
                        x-bind:disabled="!$wire.newMessageText || $wire.newMessageText.length < 10"
                        class="flex items-center gap-2">
                        <x-lucide-image class="w-4 h-4" />
                        Generar Imagen
                    </x-button>

                    <x-button 
                        type="button"
                        variant="outline"
                        wire:click="saveManualMessageForLater"
                        x-bind:disabled="!$wire.newMessageText || $wire.newMessageText.length < 10"
                        class="flex items-center gap-2">
                        <x-lucide-save class="w-4 h-4" />
                        Agregar para Aprobar
                    </x-button>

                    <x-button 
                        type="button"
                        variant="ghost"
                        @click="showCreateModal = false">
                        Cancelar
                    </x-button>
                </div>
            </div>
            </div>
        </div>
    </template>

    <!-- Image Generation Type Modal (Sub-modal) -->
    <template x-teleport="body">
        <div x-show="showImageGenerationModal"
             x-transition.opacity
             class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 backdrop-blur-sm"
             style="display: none;"
             @click="showImageGenerationModal = false">
            <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full mx-4"
                 @click.stop
                 x-transition.scale.80>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-foreground">Tipo de Publicación</h2>
                    <button @click="showImageGenerationModal = false" class="text-muted-foreground hover:text-foreground">
                        <x-lucide-x class="h-6 w-6" />
                    </button>
                </div>

            <p class="text-sm text-muted-foreground mb-6">
                Selecciona cómo deseas publicar este mensaje después de generar la imagen:
            </p>

            <div class="space-y-3">
                <x-button 
                    type="button"
                    wire:click="saveManualMessageAndGenerateImage('auto')"
                    wire:loading.attr="disabled"
                    wire:target="saveManualMessageAndGenerateImage"
                    @click="console.log('Auto posting clicked'); showCreateModal = false; showImageGenerationModal = false"
                    class="w-full flex items-center justify-center gap-2">
                    <x-lucide-zap class="w-4 h-4" />
                    Auto Posting
                </x-button>

                <x-button 
                    type="button"
                    variant="outline"
                    wire:click="saveManualMessageAndGenerateImage('manual')"
                    wire:loading.attr="disabled"
                    wire:target="saveManualMessageAndGenerateImage"
                    @click="console.log('Manual posting clicked'); showCreateModal = false; showImageGenerationModal = false"
                    class="w-full flex items-center justify-center gap-2">
                    <x-lucide-hand class="w-4 h-4" />
                    Manual Posting
                </x-button>

                <x-button 
                    type="button"
                    variant="ghost"
                    @click="showImageGenerationModal = false; console.log('Image modal cancelled')"
                    class="w-full">
                    Cancelar
                </x-button>
            </div>
            </div>
        </div>
    </template>
</div>

