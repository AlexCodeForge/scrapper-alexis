<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 stats-grid mb-8">
        <!-- Pending -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-4">
                <div class="flex items-center justify-between space-x-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Pendientes</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['pending']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-clock class="h-8 w-8 text-gray-500" />
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Approved -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-4">
                <div class="flex items-center justify-between space-x-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Aprobados</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['approved_auto'] + $stats['approved_manual']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-check-circle class="h-8 w-8 text-blue-500" />
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Rejected -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-4">
                <div class="flex items-center justify-between space-x-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Rechazados</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['rejected']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-x-circle class="h-8 w-8 text-red-500" />
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Estado
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-y divide-border">
                        @forelse ($messages as $message)
                            <tr class="hover:bg-accent/50 transition-colors">
                                <td class="px-4 py-4">
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
                                            <p class="text-sm text-foreground">
                                                {{ $message->message_text }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-muted-foreground">
                                        <div class="flex items-center">
                                            <x-lucide-calendar class="h-4 w-4 mr-1" />
                                            {{ $message->scraped_at ? $message->scraped_at->format('d/m/Y') : 'N/A' }}
                                        </div>
                                        <div class="flex items-center text-xs mt-1">
                                            <x-lucide-clock class="h-3 w-3 mr-1" />
                                            {{ $message->scraped_at ? $message->scraped_at->format('H:i') : 'N/A' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button 
                                            wire:click="approveMessage({{ $message->id }})"
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                            Aprobar
                                        </button>
                                        <button 
                                            wire:click="approveForManual({{ $message->id }})"
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            Manual
                                        </button>
                                        <button 
                                            wire:click="rejectMessage({{ $message->id }})"
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                                            Rechazar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
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
</div>

