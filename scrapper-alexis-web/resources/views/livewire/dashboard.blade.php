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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 stats-grid mb-8">
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

                <!-- Posted to Twitter -->
                <x-card class="hover:shadow-md transition-shadow">
                    <x-card.content class="p-4">
                        <div class="flex items-center justify-between space-x-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-muted-foreground truncate">Publicados en Twitter</p>
                                <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['posted_to_twitter']) }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-lucide-send class="h-8 w-8 text-blue-500" />
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

                <!-- Active Profiles -->
                <x-card class="hover:shadow-md transition-shadow">
                    <x-card.content class="p-4">
                        <div class="flex items-center justify-between space-x-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-muted-foreground truncate">Perfiles Activos</p>
                                <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['active_profiles']) }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-lucide-users class="h-8 w-8 text-purple-500" />
                            </div>
                        </div>
                    </x-card.content>
                </x-card>
            </div>

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

                        <x-button wire:click="runScript('twitter')" class="flex-1 h-14 text-base bg-sky-500 hover:bg-sky-600 text-white shadow-lg hover:shadow-xl">
                            <x-lucide-twitter class="mr-3 h-6 w-6" />
                            Ejecutar Publicador Twitter
                        </x-button>
                    </div>
                </x-card.content>
            </x-card>

            <!-- Recent Messages -->
            <x-card x-data="{ activeTab: 'all' }">
                <x-card.header>
                    <x-card.title class="text-xl">Mensajes Recientes</x-card.title>
                    <x-card.description>
                        <span x-show="activeTab === 'all'">Últimos 5 mensajes extraídos</span>
                        <span x-show="activeTab === 'posted'" x-cloak>Últimos 5 mensajes publicados</span>
                        <span x-show="activeTab === 'pending'" x-cloak>Mensajes pendientes y programados</span>
                    </x-card.description>
                </x-card.header>
                <x-card.content class="p-0">
                    <!-- Tabs -->
                    <div class="border-b border-border px-6">
                        <div class="flex gap-4">
                            <button
                                @click="activeTab = 'all'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors cursor-pointer"
                                :class="activeTab === 'all' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                                Todos
                            </button>
                            <button
                                @click="activeTab = 'posted'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors cursor-pointer"
                                :class="activeTab === 'posted' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                                Publicados
                            </button>
                            <button
                                @click="activeTab = 'pending'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors cursor-pointer"
                                :class="activeTab === 'pending' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'">
                                Pendientes
                            </button>
                        </div>
                    </div>

                    <!-- All Messages -->
                    <div x-show="activeTab === 'all'" class="divide-y divide-border">
                        @forelse ($allMessages as $message)
                            <div class="px-6 py-5 hover:bg-accent/50 transition-colors">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base text-foreground line-clamp-2">{{ Str::limit($message->message_text, 150) }}</p>
                                        <p class="text-sm text-muted-foreground mt-2 flex items-center gap-1">
                                            <x-lucide-clock class="h-4 w-4" />
                                            {{ $message->scraped_at->diffForHumans() }}
                                            @if ($message->profile)
                                                · {{ $message->profile->username }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 flex items-center gap-2">
                                        @if ($message->posted_to_twitter)
                                            <x-badge variant="default">Publicado</x-badge>
                                        @else
                                            <x-badge variant="secondary">Pendiente</x-badge>
                                        @endif

                                        @if ($message->image_generated)
                                            <x-badge variant="outline">
                                                <x-lucide-image class="h-3 w-3 mr-1" />
                                                Imagen
                                            </x-badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-16 text-center">
                                <x-lucide-inbox class="mx-auto h-16 w-16 text-muted-foreground mb-4" />
                                <p class="text-base text-muted-foreground">No se encontraron mensajes</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Posted Messages -->
                    <div x-show="activeTab === 'posted'" x-cloak class="divide-y divide-border">
                        @forelse ($postedMessages as $message)
                            <div class="px-6 py-5 hover:bg-accent/50 transition-colors">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base text-foreground line-clamp-2">{{ Str::limit($message->message_text, 150) }}</p>
                                        <p class="text-sm text-muted-foreground mt-2 flex items-center gap-1">
                                            <x-lucide-clock class="h-4 w-4" />
                                            {{ $message->posted_at ? $message->posted_at->diffForHumans() : $message->scraped_at->diffForHumans() }}
                                            @if ($message->profile)
                                                · {{ $message->profile->username }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 flex items-center gap-2">
                                        <x-badge variant="default">Publicado</x-badge>

                                        @if ($message->image_generated)
                                            <x-badge variant="outline">
                                                <x-lucide-image class="h-3 w-3 mr-1" />
                                                Imagen
                                            </x-badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-16 text-center">
                                <x-lucide-inbox class="mx-auto h-16 w-16 text-muted-foreground mb-4" />
                                <p class="text-base text-muted-foreground">No hay mensajes publicados</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Pending Messages -->
                    <div x-show="activeTab === 'pending'" x-cloak class="divide-y divide-border">
                        @forelse ($pendingMessages as $message)
                            <div class="px-6 py-5 hover:bg-accent/50 transition-colors">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base text-foreground line-clamp-2">{{ Str::limit($message->message_text, 150) }}</p>
                                        <p class="text-sm text-muted-foreground mt-2 flex items-center gap-1">
                                            <x-lucide-clock class="h-4 w-4" />
                                            @if ($message->posted_to_twitter && $message->posted_at && $message->posted_at->isFuture())
                                                Se publicará {{ $message->posted_at->diffForHumans() }}
                                            @else
                                                {{ $message->scraped_at->diffForHumans() }}
                                            @endif
                                            @if ($message->profile)
                                                · {{ $message->profile->username }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 flex items-center gap-2">
                                        @if ($message->posted_to_twitter && $message->posted_at && $message->posted_at->isFuture())
                                            <x-badge variant="default">
                                                <x-lucide-calendar-clock class="h-3 w-3 mr-1" />
                                                Programado
                                            </x-badge>
                                        @else
                                            <x-badge variant="secondary">Pendiente</x-badge>
                                        @endif

                                        @if ($message->image_generated)
                                            <x-badge variant="outline">
                                                <x-lucide-image class="h-3 w-3 mr-1" />
                                                Imagen
                                            </x-badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-16 text-center">
                                <x-lucide-inbox class="mx-auto h-16 w-16 text-muted-foreground mb-4" />
                                <p class="text-base text-muted-foreground">No hay mensajes pendientes</p>
                            </div>
                        @endforelse
                    </div>
                </x-card.content>
            </x-card>
    </div>
