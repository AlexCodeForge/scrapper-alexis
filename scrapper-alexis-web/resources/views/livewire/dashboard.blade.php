<div>
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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 stats-grid mb-8">
                <!-- Total Messages -->
                <x-card class="hover:shadow-md transition-shadow">
                    <x-card.content class="p-4">
                        <div class="flex items-center justify-between space-x-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-muted-foreground truncate">Mensajes Totales</p>
                                <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['total_messages']) }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-lucide-message-square class="h-8 w-8 text-muted-foreground" />
                            </div>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Images Generated -->
                <x-card class="hover:shadow-md transition-shadow">
                    <x-card.content class="p-4">
                        <div class="flex items-center justify-between space-x-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-muted-foreground truncate">Imágenes Generadas</p>
                                <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['images_generated']) }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-lucide-image class="h-8 w-8 text-green-500" />
                            </div>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Approved for Page -->
                <x-card class="hover:shadow-md transition-shadow">
                    <x-card.content class="p-4">
                        <div class="flex items-center justify-between space-x-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-muted-foreground truncate">Aprobadas para Página</p>
                                <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['approved_for_page']) }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-lucide-check-circle class="h-8 w-8 text-orange-500" />
                            </div>
                        </div>
                    </x-card.content>
                </x-card>

                <!-- Posted to Page -->
                <x-card class="hover:shadow-md transition-shadow">
                    <x-card.content class="p-4">
                        <div class="flex items-center justify-between space-x-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-muted-foreground truncate">Publicadas en Página</p>
                                <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['posted_to_page']) }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-lucide-facebook class="h-8 w-8 text-blue-600" />
                            </div>
                        </div>
                    </x-card.content>
                </x-card>
            </div>

            <!-- Posted Content Widget (Date Filtered) -->
            <x-card class="mb-8">
                <x-card.header>
                    <!-- Single Row Layout: Title, Count Widget & Date Filter -->
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <!-- Title and Description -->
                        <div class="flex-shrink-0">
                            <x-card.title class="text-xl">Contenido Publicado</x-card.title>
                            <x-card.description>Mensajes publicados en la página</x-card.description>
                        </div>
                        
                        <!-- Post Count & Date Filter -->
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">Publicaciones en este período:</span>
                                <span class="text-lg font-semibold text-foreground">{{ number_format($postedStats['count']) }}</span>
                            </div>
                            <x-select wire:model.live="dateFilter" class="w-48">
                                <option value="today">Hoy</option>
                                <option value="week">Últimos 7 días</option>
                                <option value="month">Últimos 30 días</option>
                                <option value="custom">Rango Personalizado</option>
                            </x-select>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-4">
                        <!-- Custom Date Range -->
                        @if($dateFilter === 'custom')
                            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center bg-accent/50 p-4 rounded-lg border border-border">
                                <div class="flex-1 w-full sm:w-auto">
                                    <label class="block text-xs font-medium text-muted-foreground mb-1">Fecha Inicio</label>
                                    <input type="date" 
                                           wire:model.live="customStartDate"
                                           class="w-full px-3 py-2 text-sm border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div class="flex-1 w-full sm:w-auto">
                                    <label class="block text-xs font-medium text-muted-foreground mb-1">Fecha Fin</label>
                                    <input type="date" 
                                           wire:model.live="customEndDate"
                                           class="w-full px-3 py-2 text-sm border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div class="self-end">
                                    <x-button wire:click="$refresh" size="sm" class="whitespace-nowrap">
                                        <x-lucide-search class="h-4 w-4 mr-2" />
                                        Aplicar
                                    </x-button>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card.header>
                <x-card.content class="p-6">
                    @if($postedMessagesFiltered->count() > 0)
                        <!-- Messages Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-border bg-accent/50">
                                        <th class="text-left p-3 text-sm font-semibold text-foreground">Mensaje</th>
                                        <th class="text-left p-3 text-sm font-semibold text-foreground">Fecha de Publicación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($postedMessagesFiltered as $message)
                                        <tr class="border-b border-border hover:bg-accent/30 transition-colors">
                                            <td class="p-3 text-sm text-foreground">
                                                <div class="max-w-2xl">
                                                    {{ Str::limit($message->message_text, 150) }}
                                                </div>
                                            </td>
                                            <td class="p-3 text-sm text-foreground whitespace-nowrap">
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ $message->posted_to_page_at->format('d/m/Y') }}</span>
                                                    <span class="text-xs text-muted-foreground">{{ $message->posted_to_page_at->format('h:i A') }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @else
                        <div class="text-center py-12">
                            <x-lucide-calendar-x class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                            <p class="text-base text-muted-foreground">No hay contenido publicado en este período</p>
                        </div>
                    @endif
                </x-card.content>
            </x-card>

            <!-- Manual Run Buttons -->
            <x-card class="mb-8">
                <x-card.header>
                    <x-card.title class="text-xl">Acciones Manuales</x-card.title>
                    <x-card.description>Ejecutar scripts manualmente bajo demanda</x-card.description>
                </x-card.header>
                <x-card.content>
                    <div class="flex flex-col action-buttons-container gap-4">
                        <x-button wire:click="runScript('facebook')" class="flex-1 h-14 text-base bg-blue-600 hover:bg-blue-700 text-white shadow-lg hover:shadow-xl">
                            <x-lucide-facebook class="mr-3 h-6 w-6" />
                            Ejecutar Scraper Facebook
                        </x-button>

                        <x-button wire:click="generateImages" class="flex-1 h-14 text-base bg-purple-600 hover:bg-purple-700 text-white shadow-lg hover:shadow-xl">
                            <x-lucide-image class="mr-3 h-6 w-6" />
                            Generar Imágenes
                        </x-button>

                        <x-button wire:click="postToPage" class="flex-1 h-14 text-base bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg hover:shadow-xl">
                            <x-lucide-share-2 class="mr-3 h-6 w-6" />
                            Publicar en Página de Facebook
                        </x-button>
                    </div>
                </x-card.content>
            </x-card>
    </div>
