<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <style>
        @media (min-width: 1024px) {
            .next-execution-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }
    </style>
    <!-- Header with Controls -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Logs del Sistema</h2>
                <p class="text-sm text-gray-600 mt-1">Monitorear la ejecución de los scrapers</p>
            </div>
            <div class="flex items-center gap-3"
                 x-data="{ autoRefresh: @entangle('autoRefresh').live }"
                 x-init="setInterval(() => { if(autoRefresh) $wire.$refresh() }, 3000)">
                <!-- Download Button -->
                <x-button wire:click="downloadLog" variant="outline" size="sm">
                    <x-lucide-download class="h-4 w-4 mr-1" />
                    Descargar
                </x-button>
            </div>
        </div>
    </div>

    <!-- Next Execution Cards -->
    <div class="next-execution-grid grid grid-cols-1 gap-4 mb-6">
        @foreach ($this->nextExecutions as $jobKey => $execution)
            @php
                $nextRunIso = $execution['next_run_at'] ? $execution['next_run_at']->toIso8601String() : null;
                $iconMap = [
                    'facebook' => 'facebook',
                    'page_poster' => 'image',
                    'image_generator' => 'image-plus',
                ];
                $colorMap = [
                    'facebook' => 'text-blue-600',
                    'page_poster' => 'text-indigo-600',
                    'image_generator' => 'text-green-600',
                ];
            @endphp
            <x-card class="hover:shadow-md transition-shadow">
                <x-card.content class="p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            @if ($iconMap[$jobKey] === 'facebook')
                                <x-lucide-facebook class="h-5 w-5 {{ $colorMap[$jobKey] }}" />
                            @elseif ($iconMap[$jobKey] === 'image')
                                <x-lucide-image class="h-5 w-5 {{ $colorMap[$jobKey] }}" />
                            @else
                                <x-lucide-image-plus class="h-5 w-5 {{ $colorMap[$jobKey] }}" />
                            @endif
                            <h3 class="font-semibold text-foreground text-sm">{{ $execution['name'] }}</h3>
                        </div>
                        @if ($execution['next_run_at'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Activo
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                Pendiente
                            </span>
                        @endif
                    </div>

                    @if ($execution['next_run_at'])
                        <div class="space-y-2"
                             x-data="{
                                 nextRunAt: @js($nextRunIso),
                                 timeLeft: '',
                                 updateCountdown() {
                                     if (!this.nextRunAt) {
                                         this.timeLeft = 'No programado';
                                         return;
                                     }
                                     
                                     const now = new Date();
                                     const target = new Date(this.nextRunAt);
                                     const diff = target - now;
                                     
                                     if (diff <= 0) {
                                         this.timeLeft = 'Ejecutando...';
                                         return;
                                     }
                                     
                                     const minutes = Math.floor(diff / 60000);
                                     const seconds = Math.floor((diff % 60000) / 1000);
                                     
                                     if (minutes > 60) {
                                         const hours = Math.floor(minutes / 60);
                                         const mins = minutes % 60;
                                         this.timeLeft = hours + 'h ' + mins + 'm';
                                     } else if (minutes > 0) {
                                         this.timeLeft = minutes + 'm ' + seconds + 's';
                                     } else {
                                         this.timeLeft = seconds + 's';
                                     }
                                 }
                             }"
                             x-init="updateCountdown(); setInterval(() => updateCountdown(), 1000)">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Próxima ejecución</p>
                                <p class="text-lg font-bold text-foreground">
                                    {{ $execution['next_run_at']->format('H:i:s') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">Tiempo restante</p>
                                <p class="text-2xl font-bold {{ $colorMap[$jobKey] }}" x-text="timeLeft"></p>
                            </div>
                            @if ($execution['last_run_at'])
                                <div class="pt-2 border-t border-border">
                                    <p class="text-xs text-muted-foreground">
                                        Última ejecución: {{ $execution['last_run_at']->diffForHumans() }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="py-4">
                            <p class="text-sm text-muted-foreground text-center">Esperando primera ejecución</p>
                        </div>
                    @endif
                </x-card.content>
            </x-card>
        @endforeach
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Facebook Scraper Status -->
        <x-card>
            <x-card.content class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <x-lucide-facebook class="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Facebook Scraper</h3>
                            <p class="text-sm {{ $facebookEnabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $facebookEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                            </p>
                        </div>
                    </div>
                    <x-button wire:click="selectLog('facebook')"
                              variant="{{ $selectedLog === 'facebook' ? 'default' : 'outline' }}"
                              size="sm">
                        Ver Logs
                    </x-button>
                </div>
            </x-card.content>
        </x-card>

        <!-- Image Generator Status -->
        <x-card>
            <x-card.content class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <x-lucide-image-plus class="h-6 w-6 text-green-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Image Generator</h3>
                            <p class="text-sm {{ $imageGeneratorEnabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $imageGeneratorEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                            </p>
                        </div>
                    </div>
                    <x-button wire:click="selectLog('image-generator')"
                              variant="{{ $selectedLog === 'image-generator' ? 'default' : 'outline' }}"
                              size="sm">
                        Ver Logs
                    </x-button>
                </div>
            </x-card.content>
        </x-card>

        <!-- Facebook Page Poster Status -->
        <x-card>
            <x-card.content class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <x-lucide-image class="h-6 w-6 text-indigo-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Page Poster</h3>
                            <p class="text-sm {{ $pagePosterEnabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $pagePosterEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                            </p>
                        </div>
                    </div>
                    <x-button wire:click="selectLog('page-poster')"
                              variant="{{ $selectedLog === 'page-poster' ? 'default' : 'outline' }}"
                              size="sm">
                        Ver Logs
                    </x-button>
                </div>
            </x-card.content>
        </x-card>
    </div>

    <!-- Auto Cleanup Status -->
    <x-card class="mt-6 mb-6">
        <x-card.content class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <x-lucide-trash-2 class="h-6 w-6 text-purple-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Limpieza Automática de Imágenes</h3>
                            <p class="text-sm {{ $cleanupEnabled ? 'text-green-600' : 'text-red-600' }}">
                                Estado: {{ $cleanupEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Elimina imágenes descargadas/publicadas cada {{ $cleanupDays }} días
                                @if($lastCleanupAt)
                                    • Última limpieza: {{ $lastCleanupAt->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <x-button wire:click="runCleanup" variant="outline" size="sm" class="text-purple-600 hover:bg-purple-50">
                    <x-lucide-play class="h-4 w-4 mr-1" />
                    Ejecutar Ahora
                </x-button>
            </div>
        </x-card.content>
    </x-card>

    <!-- Log Viewer -->
    <x-card>
        <x-card.header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-button wire:click="selectLog('facebook')"
                              variant="{{ $selectedLog === 'facebook' ? 'default' : 'ghost' }}"
                              size="sm">
                        <x-lucide-facebook class="h-4 w-4 mr-1" />
                        Facebook
                    </x-button>
                    <x-button wire:click="selectLog('image-generator')"
                              variant="{{ $selectedLog === 'image-generator' ? 'default' : 'ghost' }}"
                              size="sm">
                        <x-lucide-image-plus class="h-4 w-4 mr-1" />
                        Image Gen
                    </x-button>
                    <x-button wire:click="selectLog('page-poster')"
                              variant="{{ $selectedLog === 'page-poster' ? 'default' : 'ghost' }}"
                              size="sm">
                        <x-lucide-image class="h-4 w-4 mr-1" />
                        Page Poster
                    </x-button>
                    <x-button wire:click="selectLog('execution')"
                              variant="{{ $selectedLog === 'execution' ? 'default' : 'ghost' }}"
                              size="sm">
                        <x-lucide-file-text class="h-4 w-4 mr-1" />
                        Execution
                    </x-button>

                    <!-- Manual Logs Dropdown -->
                    @if(count($manualLogs) > 0)
                        <div x-data="{ open: false }" class="relative">
                            <x-button @click="open = !open"
                                      variant="{{ $selectedLog === 'manual' ? 'default' : 'ghost' }}"
                                      size="sm">
                                <x-lucide-play-circle class="h-4 w-4 mr-1" />
                                Manual Runs
                                <x-lucide-chevron-down class="h-4 w-4 ml-1" />
                            </x-button>

                            <div x-show="open"
                                 @click.away="open = false"
                                 x-transition
                                 class="absolute z-10 mt-2 w-64 bg-white rounded-md shadow-lg border"
                                 style="max-height: 16rem; overflow-y: auto;">
                                <div class="py-1">
                                    @foreach($manualLogs as $log)
                                        <button wire:click="selectManualLog('{{ $log['name'] }}')"
                                                @click="open = false"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 {{ $selectedManualLog === $log['name'] ? 'bg-blue-50 text-blue-700' : 'text-gray-700' }}">
                                            <div class="font-medium">{{ $log['name'] }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ \Illuminate\Support\Carbon::createFromTimestamp($log['modified'])->format('Y-m-d H:i:s') }} • {{ number_format($log['size'] / 1024, 2) }} KB
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Lines Selector -->
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Líneas:</label>
                    <select wire:model.live="lines" class="px-2 py-1 text-sm border border-gray-300 rounded-md">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="500">500</option>
                        <option value="1000">1000</option>
                    </select>
                </div>
            </div>
        </x-card.header>
        <x-card.content class="p-0">
            <!-- Log Content -->
            <div class="bg-gray-900 text-green-400 p-4 font-mono text-xs sm:text-sm overflow-x-auto overflow-y-auto" style="max-height: 600px;">
                <pre class="whitespace-pre">{{ $logContent }}</pre>
            </div>
        </x-card.content>
        <x-card.footer class="text-xs text-gray-500">
            <div class="flex items-center justify-between">
                <span>
                    Mostrando últimas {{ $lines }} líneas
                    @if($selectedLog === 'manual' && $selectedManualLog)
                        de {{ $selectedManualLog }}
                    @else
                        de cron_{{ $selectedLog }}.log
                    @endif
                </span>
                <span wire:poll.3s>
                    Actualizado: {{ now()->format('H:i:s') }}
                </span>
            </div>
        </x-card.footer>
    </x-card>

    <!-- Help Section -->
    <x-card class="mt-6">
        <x-card.header>
            <x-card.title class="flex items-center gap-2">
                <x-lucide-info class="h-5 w-5" />
                Información de Logs
            </x-card.title>
        </x-card.header>
        <x-card.content>
            <div class="space-y-2 text-sm text-gray-600">
                <p><strong>Facebook:</strong> Logs de scraping de perfiles de Facebook (cron_facebook.log)</p>
                <p><strong>Page Poster:</strong> Logs de publicaciones en página de Facebook (page_poster_*.log)</p>
                <p><strong>Execution:</strong> Logs generales de ejecución de cron (cron_execution.log)</p>
                <p><strong>Manual Runs:</strong> Logs de ejecuciones manuales desde el dashboard</p>
            </div>
        </x-card.content>
    </x-card>
</div>

