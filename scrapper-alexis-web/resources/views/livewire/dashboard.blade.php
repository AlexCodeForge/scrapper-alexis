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
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <x-card.title class="text-xl">Contenido Publicado</x-card.title>
                                <x-card.description>Galería de imágenes publicadas en la página</x-card.description>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-select wire:model.live="dateFilter" class="w-48">
                                    <option value="today">Hoy</option>
                                    <option value="week">Últimos 7 días</option>
                                    <option value="month">Últimos 30 días</option>
                                    <option value="custom">Rango Personalizado</option>
                                </x-select>
                            </div>
                        </div>
                        
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
                    @if($postedImagesFiltered->count() > 0)
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            @foreach($postedImagesFiltered as $image)
                                <div class="group relative aspect-square rounded-lg overflow-hidden bg-accent hover:ring-2 hover:ring-primary transition-all cursor-pointer">
                                    @if($image->image_url)
                                        <img src="{{ $image->image_url }}" 
                                             alt="Posted image" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <div class="text-white text-center p-2">
                                                <p class="text-xs font-medium">{{ $image->posted_to_page_at->format('d M Y') }}</p>
                                                <p class="text-xs">{{ $image->posted_to_page_at->format('H:i') }}</p>
                                            </div>
                                        </div>
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <x-lucide-image class="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <!-- View All Link -->
                        <div class="mt-6 text-center">
                            <a href="{{ route('images') }}?filter=posted" class="inline-flex items-center gap-2 text-sm text-primary hover:underline">
                                Ver todas las imágenes publicadas
                                <x-lucide-arrow-right class="h-4 w-4" />
                            </a>
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
