<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
    @pagination-changed.window="clearOnPagination()"
    @download-ready.window="window.location.href = $event.detail.url"
    x-data="{
            localSelected: [],
            filter: @entangle('filter').live,
            perPage: @entangle('perPage').live,

            getVisibleIds() {
                const cards = this.$root.querySelectorAll('[data-image-id]');
                return Array.from(cards).map(card => parseInt(card.getAttribute('data-image-id')));
            },

            get allSelected() {
                const visibleIds = this.getVisibleIds();
                return visibleIds.length > 0 && visibleIds.every(id => this.localSelected.includes(id));
            },

            selectAllOnPage() {
                const visibleIds = this.getVisibleIds();
                console.log('Alpine: Select all on page', visibleIds);

                if (this.allSelected) {
                    this.localSelected = this.localSelected.filter(id => !visibleIds.includes(id));
                } else {
                    visibleIds.forEach(id => {
                        if (!this.localSelected.includes(id)) {
                            this.localSelected.push(id);
                        }
                    });
                }

                console.log('Alpine: Selected items after select all', this.localSelected);
                this.$wire.set('selected', this.localSelected);
            },

            init() {
                console.log('Alpine: Images initialized', this.localSelected);
                this.$wire.set('selected', []);
            },

            clearOnPagination() {
                console.log('Alpine: Clearing selections due to pagination');
                this.localSelected = [];
                this.$wire.set('selected', []);
            },

            toggleSelection(id) {
                console.log('Alpine: Toggle selection', id);
                if (this.localSelected.includes(id)) {
                    this.localSelected = this.localSelected.filter(i => i !== id);
                } else {
                    this.localSelected.push(id);
                }
                console.log('Alpine: Selected items', this.localSelected);
                this.$wire.set('selected', this.localSelected);
            },

            approveSelectedForAuto() {
                console.log('Alpine: Bulk approve for auto-post', this.localSelected);
                if (this.localSelected.length === 0) return;

                const toApprove = [...this.localSelected];
                this.localSelected = [];

                this.$wire.approveSelectedForAuto();
            },

            approveSelectedForManual() {
                console.log('Alpine: Bulk approve for manual post', this.localSelected);
                if (this.localSelected.length === 0) return;

                const toApprove = [...this.localSelected];
                this.localSelected = [];

                this.$wire.approveSelectedForManual();
            },

            rejectSelected() {
                console.log('Alpine: Bulk reject', this.localSelected);
                if (this.localSelected.length === 0) return;
                if (!confirm(`¿Estás seguro de que quieres rechazar ${this.localSelected.length} imágenes?`)) return;

                const toReject = [...this.localSelected];
                this.localSelected = [];

                this.$wire.rejectSelected();
            },

            downloadSelected() {
                console.log('Alpine: Bulk download', this.localSelected);
                if (this.localSelected.length === 0) return;

                this.$wire.downloadSelected();
            },

            deleteSelected() {
                console.log('Alpine: Bulk delete', this.localSelected);
                if (this.localSelected.length === 0) return;
                if (!confirm(`¿Estás seguro de que quieres eliminar ${this.localSelected.length} imágenes?`)) return;

                const toDelete = [...this.localSelected];
                this.localSelected = [];

                this.$wire.deleteSelected();
            },

            clearSelections() {
                console.log('Alpine: Clearing selections');
                this.localSelected = [];
                this.$wire.set('selected', []);
            }
        }">
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

        <!-- Approved Manual -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-4">
                <div class="flex items-center justify-between space-x-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Aprobadas</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['approved_manual']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-check-circle class="h-8 w-8 text-blue-500" />
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Posted -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="filter, perPage">
            <x-card.content class="p-4">
                <div class="flex items-center justify-between space-x-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-muted-foreground truncate">Publicadas</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ number_format($stats['posted']) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <x-lucide-check-circle-2 class="h-8 w-8 text-purple-500" />
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
                        <option value="pending">Pendientes</option>
                        <option value="approved_auto">Aprobadas (Auto)</option>
                        <option value="approved_manual">Aprobadas (Manual)</option>
                        <option value="posted">Publicadas</option>
                        <option value="all">Todas</option>
                    </x-select>

                    <x-select wire:model.live="perPage" class="w-48">
                        <option value="12">12 por página</option>
                        <option value="24">24 por página</option>
                        <option value="48">48 por página</option>
                    </x-select>
                </div>

                <!-- Bulk Actions -->
                <div class="flex flex-wrap gap-2">
                    <x-button @click="selectAllOnPage()"
                        variant="outline"
                        size="sm"
                        class="cursor-pointer">
                        <span x-show="!allSelected">Seleccionar todo</span>
                        <span x-show="allSelected">Deseleccionar todo</span>
                    </x-button>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="approveSelectedForAuto()"
                            variant="outline"
                            size="sm"
                            wire:loading.attr="disabled"
                            wire:target="approveSelectedForAuto"
                            class="cursor-pointer bg-green-600 text-white hover:bg-green-700">
                            <x-lucide-zap class="mr-2 h-4 w-4" />
                            Auto-Post (<span x-text="localSelected.length"></span>)
                        </x-button>
                    </template>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="approveSelectedForManual()"
                            variant="outline"
                            size="sm"
                            wire:loading.attr="disabled"
                            wire:target="approveSelectedForManual"
                            class="cursor-pointer bg-blue-600 text-white hover:bg-blue-700">
                            <x-lucide-hand class="mr-2 h-4 w-4" />
                            Manual (<span x-text="localSelected.length"></span>)
                        </x-button>
                    </template>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="rejectSelected()"
                            variant="outline"
                            size="sm"
                            wire:loading.attr="disabled"
                            wire:target="rejectSelected"
                            class="cursor-pointer">
                            <x-lucide-x class="mr-2 h-4 w-4" />
                            Rechazar (<span x-text="localSelected.length"></span>)
                        </x-button>
                    </template>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="downloadSelected()"
                            variant="outline"
                            size="sm"
                            wire:loading.attr="disabled"
                            wire:target="downloadSelected"
                            class="cursor-pointer">
                            <x-lucide-download class="mr-2 h-4 w-4" />
                            Descargar (<span x-text="localSelected.length"></span>)
                        </x-button>
                    </template>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="deleteSelected()"
                            variant="destructive"
                            size="sm"
                            wire:loading.attr="disabled"
                            wire:target="deleteSelected"
                            class="cursor-pointer">
                            <x-lucide-trash-2 class="mr-2 h-4 w-4" />
                            Eliminar (<span x-text="localSelected.length"></span>)
                        </x-button>
                    </template>
                </div>
            </div>
        </x-card.content>
    </x-card>

    <!-- Image Grid -->
    <div class="grid grid-cols-1 image-gallery-grid gap-6 mb-6" wire:loading.class="!hidden" wire:target="filter, perPage, gotoPage, nextPage, previousPage">
        @forelse ($messages as $message)
            <x-card
                wire:key="message-{{ $message->id }}"
                data-image-id="{{ $message->id }}"
                class="hover:shadow-lg transition-all duration-200 cursor-pointer relative"
                x-bind:class="localSelected.includes({{ $message->id }}) ? 'border-2 border-blue-500 bg-blue-50/50' : ''"
                @click="toggleSelection({{ $message->id }})">

                <!-- Selected Indicator -->
                <div x-show="localSelected.includes({{ $message->id }})"
                     class="absolute top-3 right-3 z-10 bg-blue-500 text-white rounded-full p-2 shadow-lg"
                     x-transition>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>

                <!-- Image -->
                <div class="group">
                    @if ($message->image_url)
                        <div class="relative overflow-hidden rounded-t-xl bg-accent h-48 min-h-48 flex items-center justify-center">
                            <img src="{{ $message->image_url }}"
                                alt="Message image"
                                class="w-full h-full object-contain transition-transform duration-200">
                        </div>
                    @else
                        <div class="w-full h-48 min-h-48 rounded-t-xl bg-muted flex items-center justify-center">
                            <span class="text-muted-foreground">Sin imagen</span>
                        </div>
                    @endif
                </div>

                <!-- Info -->
                <x-card.content class="p-3">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-muted-foreground">{{ $message->scraped_at->diffForHumans() }}</p>
                        @if ($message->posted_to_page)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                <x-lucide-check-circle-2 class="w-3 h-3 mr-1" />
                                Publicada
                            </span>
                        @elseif ($message->approved_for_posting && $message->auto_post_enabled)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                <x-lucide-zap class="w-3 h-3 mr-1" />
                                Auto-Post
                            </span>
                        @elseif ($message->approved_for_posting && !$message->auto_post_enabled)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                <x-lucide-hand class="w-3 h-3 mr-1" />
                                Manual
                            </span>
                        @elseif ($message->approved_for_posting === false && $message->approved_at)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                <x-lucide-x-circle class="w-3 h-3 mr-1" />
                                Rechazada
                            </span>
                        @endif
                    </div>

                    <!-- Message Text Preview -->
                    <p class="text-xs text-muted-foreground mb-3 line-clamp-2">{{ $message->message_text }}</p>

                    <!-- Actions -->
                    <div class="flex flex-col space-y-2">
                        @if (!$message->posted_to_page)
                            {{-- Show approval buttons only if NOT approved --}}
                            @if (!$message->approved_for_posting)
                                <div class="flex space-x-2">
                                    <x-button @click.stop="$wire.approveForAutoPost({{ $message->id }})"
                                        variant="outline"
                                        size="sm"
                                        wire:loading.attr="disabled"
                                        wire:target="approveForAutoPost({{ $message->id }})"
                                        class="flex-1 bg-green-600 text-white hover:bg-green-700">
                                        <x-lucide-zap class="mr-1 h-3 w-3" />
                                        Auto-Post
                                    </x-button>

                                    <x-button @click.stop="$wire.approveForManualPost({{ $message->id }})"
                                        variant="outline"
                                        size="sm"
                                        wire:loading.attr="disabled"
                                        wire:target="approveForManualPost({{ $message->id }})"
                                        class="flex-1 bg-blue-600 text-white hover:bg-blue-700">
                                        <x-lucide-hand class="mr-1 h-3 w-3" />
                                        Manual
                                    </x-button>
                                </div>

                                <x-button @click.stop="$wire.rejectImage({{ $message->id }})"
                                    variant="outline"
                                    size="sm"
                                    wire:loading.attr="disabled"
                                    wire:target="rejectImage({{ $message->id }})"
                                    class="w-full">
                                    <x-lucide-x class="mr-1 h-3 w-3" />
                                    Rechazar
                                </x-button>
                            @else
                                {{-- Already approved, show status --}}
                                <div class="text-center py-2 text-sm">
                                    @if ($message->auto_post_enabled)
                                        <p class="text-green-600 font-medium">✓ Lista para auto-publicación</p>
                                    @else
                                        <p class="text-blue-600 font-medium">✓ Lista para publicación manual</p>
                                    @endif
                                </div>
                            @endif
                        @else
                            <div class="text-center py-2 text-sm">
                                <p class="text-purple-600 font-medium">✓ Publicada {{ $message->posted_to_page_at->diffForHumans() }}</p>
                            </div>
                        @endif

                        <!-- Download button for all images -->
                        <x-button @click.stop="$wire.downloadImage({{ $message->id }})"
                            variant="outline"
                            size="sm"
                            class="w-full">
                            <x-lucide-download class="mr-1 h-3 w-3" />
                            Descargar
                        </x-button>
                    </div>
                </x-card.content>
            </x-card>
        @empty
            <div class="col-span-full text-center py-12">
                <p class="text-muted-foreground">No hay imágenes para mostrar</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if ($messages->hasPages())
        <div class="mt-6">
            {{ $messages->links() }}
        </div>
    @endif
</div>
