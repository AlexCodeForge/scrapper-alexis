<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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

    <!-- Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Mensajes Scrapeados</h2>
                <p class="text-sm text-gray-600 mt-1">Todos los mensajes recopilados de perfiles de Facebook</p>
            </div>
            <div>
                <!-- Per Page -->
                <select wire:model.live="perPage"
                        class="block w-full px-3 py-2.5 border border-input rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-ring focus:border-input sm:text-sm transition duration-150 ease-in-out">
                    <option value="10">10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                    <option value="100">100 por página</option>
                </select>
            </div>
        </div>
    </div>

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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Mensaje
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Perfil
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Fecha
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-background divide-y divide-border">
                        @forelse ($messages as $message)
                            <tr class="hover:bg-accent/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-start space-x-3">
                                        @if ($message->image_generated)
                                            <div class="flex-shrink-0">
                                                <x-lucide-image class="h-5 w-5 text-green-500" />
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
                                    <div class="flex items-center">
                                        <x-lucide-user class="h-4 w-4 text-muted-foreground mr-2" />
                                        <span class="text-sm font-medium text-foreground">
                                            {{ $message->profile?->username ?? 'N/A' }}
                                        </span>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center">
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

