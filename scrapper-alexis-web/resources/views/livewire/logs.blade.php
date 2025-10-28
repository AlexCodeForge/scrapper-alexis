<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header with Controls -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Logs del Sistema</h2>
                <p class="text-sm text-gray-600 mt-1">Monitorear la ejecución de los scrapers</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Auto-refresh Toggle -->
                <div x-data="{ autoRefresh: @entangle('autoRefresh').live }"
                     x-init="setInterval(() => { if(autoRefresh) $wire.$refresh() }, 3000)"
                     class="flex items-center gap-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="autoRefresh" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                    <span class="text-sm text-gray-700">Auto-refresh</span>
                </div>

                <!-- Download Button -->
                <x-button wire:click="downloadLog" variant="outline" size="sm">
                    <x-lucide-download class="h-4 w-4 mr-1" />
                    Descargar
                </x-button>
            </div>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <!-- Facebook Status -->
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

        <!-- Twitter Status -->
        <x-card>
            <x-card.content class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <x-lucide-twitter class="h-6 w-6 text-blue-400" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Twitter Poster</h3>
                            <p class="text-sm {{ $twitterEnabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $twitterEnabled ? '🟢 Activo' : '🔴 Detenido' }}
                            </p>
                        </div>
                    </div>
                    <x-button wire:click="selectLog('twitter')"
                              variant="{{ $selectedLog === 'twitter' ? 'default' : 'outline' }}"
                              size="sm">
                        Ver Logs
                    </x-button>
                </div>
            </x-card.content>
        </x-card>
    </div>

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
                    <x-button wire:click="selectLog('twitter')"
                              variant="{{ $selectedLog === 'twitter' ? 'default' : 'ghost' }}"
                              size="sm">
                        <x-lucide-twitter class="h-4 w-4 mr-1" />
                        Twitter
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
                                 class="absolute z-10 mt-2 w-64 bg-white rounded-md shadow-lg border">
                                <div class="py-1 max-h-64 overflow-y-auto">
                                    @foreach($manualLogs as $log)
                                        <button wire:click="selectManualLog('{{ $log['name'] }}')"
                                                @click="open = false"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 {{ $selectedManualLog === $log['name'] ? 'bg-blue-50 text-blue-700' : 'text-gray-700' }}">
                                            <div class="font-medium">{{ $log['name'] }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ date('Y-m-d H:i:s', $log['modified']) }} • {{ number_format($log['size'] / 1024, 2) }} KB
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
                <p><strong>Twitter:</strong> Logs de publicaciones en Twitter (cron_twitter.log)</p>
                <p><strong>Execution:</strong> Logs generales de ejecución de cron (cron_execution.log)</p>
                <p><strong>Manual Runs:</strong> Logs de ejecuciones manuales desde el dashboard</p>
                <p class="text-xs text-gray-500 mt-4">💡 Tip: Activa el auto-refresh para monitorear en tiempo real</p>
            </div>
        </x-card.content>
    </x-card>
</div>

