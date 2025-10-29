<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
    @pagination-changed.window="clearOnPagination()"
    @page-reload.window="setTimeout(() => { window.location.reload(); }, 1000)"
    x-data="{
            localSelected: [],
            deletedIds: [],
            showProcessingModal: false,
            processingAction: '',
            approvalFilter: @entangle('approvalFilter').live,
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
                console.log('Alpine: Posting Approval initialized', this.localSelected);
                this.$wire.set('selected', []);
            },

            clearOnPagination() {
                console.log('Alpine: Clearing selections due to pagination');
                this.localSelected = [];
                this.deletedIds = [];
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

            approveSelected() {
                console.log('Alpine: Bulk approve', this.localSelected);
                if (this.localSelected.length === 0) return;

                // Show processing modal
                this.processingAction = 'approve';
                this.showProcessingModal = true;
                console.log('Alpine: Showing processing modal for approve');

                this.deletedIds.push(...this.localSelected);
                const toApprove = [...this.localSelected];
                this.localSelected = [];

                this.$wire.approveSelected();
            },

            rejectSelected() {
                console.log('Alpine: Bulk reject', this.localSelected);
                if (this.localSelected.length === 0) return;
                if (!confirm(`¿Estás seguro de que quieres rechazar ${this.localSelected.length} imágenes?`)) return;

                // Show processing modal
                this.processingAction = 'reject';
                this.showProcessingModal = true;
                console.log('Alpine: Showing processing modal for reject');

                this.deletedIds.push(...this.localSelected);
                const toReject = [...this.localSelected];
                this.localSelected = [];

                this.$wire.rejectSelected();
            },

            approveImage(id) {
                console.log('Alpine: Approve single image', id);
                this.deletedIds.push(id);
                this.localSelected = this.localSelected.filter(i => i !== id);
                this.$wire.approveImage(id);
            },

            rejectImage(id) {
                console.log('Alpine: Reject single image', id);
                if (!confirm('¿Estás seguro de que quieres rechazar esta imagen?')) return;
                this.deletedIds.push(id);
                this.localSelected = this.localSelected.filter(i => i !== id);
                this.$wire.rejectImage(id);
            },

            deleteImage(id) {
                console.log('Alpine: Delete single image', id);
                if (!confirm('¿Estás seguro de que quieres eliminar esta imagen?')) return;

                this.deletedIds.push(id);
                this.localSelected = this.localSelected.filter(i => i !== id);

                this.$wire.deleteImage(id);
            },

            deleteSelected() {
                console.log('Alpine: Bulk delete', this.localSelected);
                if (this.localSelected.length === 0) return;
                if (!confirm(`¿Estás seguro de que quieres eliminar ${this.localSelected.length} imágenes?`)) return;

                this.deletedIds.push(...this.localSelected);
                const toDelete = [...this.localSelected];
                this.localSelected = [];

                this.$wire.deleteSelected().then(() => {
                    console.log('Alpine: Bulk delete completed');
                });
            },

            clearSelections() {
                console.log('Alpine: Clearing selections');
                this.localSelected = [];
                this.deletedIds = [];
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
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 stats-grid-3 mb-8">
        <!-- Pending -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="approvalFilter, perPage">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between space-x-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-muted-foreground truncate mb-1">Pendientes</p>
                        <p class="text-3xl font-bold text-foreground" wire:loading.class="invisible" wire:target="approvalFilter, perPage">{{ number_format($stats['pending']) }}</p>
                        <div wire:loading wire:target="approvalFilter, perPage" class="h-9 bg-gray-200 rounded w-24"></div>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="h-12 w-12 rounded-full bg-gray-500/10 flex items-center justify-center">
                            <x-lucide-clock class="h-6 w-6 text-gray-500" />
                        </div>
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Approved -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="approvalFilter, perPage">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between space-x-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-muted-foreground truncate mb-1">Aprobadas</p>
                        <p class="text-3xl font-bold text-foreground" wire:loading.class="invisible" wire:target="approvalFilter, perPage">{{ number_format($stats['approved']) }}</p>
                        <div wire:loading wire:target="approvalFilter, perPage" class="h-9 bg-gray-200 rounded w-24"></div>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="h-12 w-12 rounded-full bg-orange-500/10 flex items-center justify-center">
                            <x-lucide-check-circle class="h-6 w-6 text-orange-500" />
                        </div>
                    </div>
                </div>
            </x-card.content>
        </x-card>

        <!-- Rejected -->
        <x-card class="hover:shadow-md transition-shadow" wire:loading.class="animate-pulse" wire:target="approvalFilter, perPage">
            <x-card.content class="p-6">
                <div class="flex items-center justify-between space-x-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-muted-foreground truncate mb-1">Rechazadas</p>
                        <p class="text-3xl font-bold text-foreground" wire:loading.class="invisible" wire:target="approvalFilter, perPage">{{ number_format($stats['rejected']) }}</p>
                        <div wire:loading wire:target="approvalFilter, perPage" class="h-9 bg-gray-200 rounded w-24"></div>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="h-12 w-12 rounded-full bg-red-500/10 flex items-center justify-center">
                            <x-lucide-x-circle class="h-6 w-6 text-red-500" />
                        </div>
                    </div>
                </div>
            </x-card.content>
        </x-card>

    </div>

    <!-- Toolbar -->
    <x-card class="mb-6">
        <x-card.content class="p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Select All Checkbox -->
                <div class="flex items-center flex-shrink-0">
                    <input type="checkbox"
                           :checked="allSelected"
                           @click="selectAllOnPage()"
                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                           title="Seleccionar todo en esta página">
                    <label class="ml-2 text-sm text-muted-foreground cursor-pointer whitespace-nowrap" @click="selectAllOnPage()">
                        Seleccionar todo
                    </label>
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Per Page Selector -->
                    <div class="relative w-[160px]">
                        <select x-model.number="perPage"
                                class="block w-full px-3 py-2.5 border border-input rounded-lg leading-5 bg-background focus:outline-none focus:ring-2 focus:ring-ring focus:border-input sm:text-sm transition duration-150 ease-in-out">
                            <option value="10">10 por página</option>
                            <option value="15">15 por página</option>
                            <option value="20">20 por página</option>
                            <option value="25">25 por página</option>
                            <option value="30">30 por página</option>
                        </select>
                    </div>

                    <!-- Approval Filter -->
                    <div class="relative w-[180px]">
                        <select x-model="approvalFilter"
                                class="block w-full px-3 py-2.5 border border-input rounded-lg leading-5 bg-background focus:outline-none focus:ring-2 focus:ring-ring focus:border-input sm:text-sm transition duration-150 ease-in-out">
                            <option value="pending">Pendientes</option>
                            <option value="approved">Aprobadas</option>
                            <option value="rejected">Rechazadas</option>
                            <option value="posted">Publicadas</option>
                            <option value="all">Todas</option>
                        </select>
                    </div>

                    <!-- Bulk Actions (shown when items selected) -->
                    <template x-if="localSelected.length > 0">
                        <x-button @click="approveSelected()"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer hover:scale-[1.02] hover:shadow-md active:scale-[0.98] transition-all duration-150 bg-green-600 text-white hover:bg-green-700">
                            <x-lucide-check class="mr-2 h-4 w-4" />
                            Aprobar
                        </x-button>
                    </template>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="rejectSelected()"
                            variant="destructive"
                            size="sm"
                            class="cursor-pointer hover:scale-[1.02] hover:shadow-md active:scale-[0.98] transition-all duration-150">
                            <x-lucide-x class="mr-2 h-4 w-4" />
                            Rechazar
                        </x-button>
                    </template>

                    <template x-if="localSelected.length > 0">
                        <x-button @click="deleteSelected()"
                            variant="destructive"
                            size="sm"
                            class="cursor-pointer hover:scale-[1.02] hover:shadow-md active:scale-[0.98] transition-all duration-150">
                            <x-lucide-trash-2 class="mr-2 h-4 w-4" />
                            Eliminar
                        </x-button>
                    </template>
                </div>
            </div>
        </x-card.content>
    </x-card>

    <!-- Skeleton Loader -->
    <div wire:loading.delay.shorter.grid wire:target="approvalFilter, perPage, gotoPage, nextPage, previousPage" class="hidden grid-cols-1 image-gallery-grid gap-6 mb-6">
        @for ($i = 0; $i < 10; $i++)
            <x-card class="animate-pulse">
                <!-- Skeleton Tweet Image (matches actual card) -->
                <div class="relative overflow-hidden rounded-t-xl bg-gray-200 h-48 min-h-48 flex items-center justify-center">
                    <!-- Simulated tweet content overlay on skeleton -->
                    <div class="absolute top-4 left-4 flex items-start space-x-3 w-3/4">
                        <!-- Profile avatar skeleton -->
                        <div class="w-12 h-12 rounded-full bg-gray-300 flex-shrink-0"></div>
                        <div class="flex-1">
                            <!-- Name skeleton -->
                            <div class="h-4 bg-gray-300 rounded w-32 mb-2"></div>
                            <!-- Username skeleton -->
                            <div class="h-3 bg-gray-300 rounded w-24 mb-3"></div>
                            <!-- Tweet text skeleton (3 lines) -->
                            <div class="h-3 bg-gray-300 rounded w-full mb-2"></div>
                            <div class="h-3 bg-gray-300 rounded w-5/6 mb-2"></div>
                            <div class="h-3 bg-gray-300 rounded w-4/6"></div>
                        </div>
                    </div>
                </div>

                <!-- Skeleton Info Section -->
                <x-card.content class="p-3">
                    <div class="flex items-center justify-between mb-3">
                        <!-- Date skeleton -->
                        <div class="h-3 bg-gray-200 rounded w-32"></div>
                        <!-- Badge skeleton (optional, so 50% opacity) -->
                        <div class="h-5 bg-gray-200 rounded w-20 opacity-50"></div>
                    </div>

                    <!-- Action buttons skeleton -->
                    <div class="flex space-x-2">
                        <div class="h-9 bg-gray-200 rounded flex-1"></div>
                        <div class="h-9 bg-gray-200 rounded w-9"></div>
                    </div>
                </x-card.content>
            </x-card>
        @endfor
    </div>

    <!-- Image Grid -->
    <div class="grid grid-cols-1 image-gallery-grid gap-6 mb-6" wire:loading.class="!hidden" wire:target="approvalFilter, perPage, gotoPage, nextPage, previousPage">
        @forelse ($messages as $message)
            <x-card
                data-image-id="{{ $message->id }}"
                x-show="!deletedIds.includes({{ $message->id }})"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-0"
                class="hover:shadow-lg transition-all duration-200 cursor-pointer relative"
                x-bind:class="localSelected.includes({{ $message->id }}) ? 'border-2 border-blue-500 bg-blue-50/50' : ''"
                @click="toggleSelection({{ $message->id }})">

                <!-- Selected Indicator -->
                <div x-show="localSelected.includes({{ $message->id }})"
                     class="absolute top-3 right-3 z-10 bg-blue-500 text-white rounded-full p-2 shadow-lg"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-0"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-0">
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
                        @if ($message->approved_for_posting && !$message->posted_to_page)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                <x-lucide-check-circle class="w-3 h-3 mr-1" />
                                Aprobada
                            </span>
                        @elseif ($message->posted_to_page)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                <x-lucide-facebook class="w-3 h-3 mr-1" />
                                Publicada
                            </span>
                        @elseif ($message->approved_for_posting === false && $message->approved_at)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                <x-lucide-x-circle class="w-3 h-3 mr-1" />
                                Rechazada
                            </span>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="flex space-x-2">
                        @if (!$message->posted_to_page)
                            <x-button @click.stop="localSelected.length === 0 ? approveImage({{ $message->id }}) : null"
                                variant="outline"
                                size="sm"
                                x-bind:disabled="localSelected.length > 0"
                                x-bind:class="localSelected.length > 0 ? 'opacity-50 cursor-not-allowed hover:scale-100 hover:shadow-none' : 'cursor-pointer hover:scale-[1.02] hover:shadow-md active:scale-[0.98]'"
                                class="flex-1 transition-all duration-150 bg-green-600 text-white hover:bg-green-700">
                                <x-lucide-check class="mr-1 h-3 w-3" />
                                Aprobar
                            </x-button>

                            <x-button @click.stop="rejectImage({{ $message->id }})"
                                variant="destructive"
                                size="sm"
                                class="cursor-pointer hover:scale-[1.02] hover:shadow-md active:scale-[0.98] transition-all duration-150">
                                <x-lucide-x class="h-3 w-3" />
                            </x-button>
                        @endif

                        @if (!$message->posted_to_page || ($message->posted_to_page && $message->posted_to_page_at && $message->posted_to_page_at->lt(now()->subDays(7))))
                            <x-button @click.stop="deleteImage({{ $message->id }})"
                                variant="destructive"
                                size="sm"
                                class="cursor-pointer hover:scale-[1.02] hover:shadow-md active:scale-[0.98] transition-all duration-150">
                                <x-lucide-trash-2 class="h-3 w-3" />
                            </x-button>
                        @endif
                    </div>
                </x-card.content>
            </x-card>
        @empty
            <div class="col-span-full">
                <x-card>
                    <x-card.content class="text-center py-12">
                        <x-lucide-image class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                        <h3 class="text-sm font-medium text-foreground">No se encontraron imágenes</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Intenta cambiar el filtro para ver más resultados.</p>
                    </x-card.content>
                </x-card>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <x-card>
        <x-card.content class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    @if ($messages->onFirstPage())
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-muted-foreground bg-muted border border-input cursor-not-allowed leading-5 rounded-md">
                            Anterior
                        </span>
                    @else
                        <button type="button" wire:click="previousPage" wire:loading.attr="disabled" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-background border border-input leading-5 rounded-md hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:border-input transition ease-in-out duration-150 cursor-pointer">
                            Anterior
                        </button>
                    @endif

                    @if ($messages->hasMorePages())
                        <button type="button" wire:click="nextPage" wire:loading.attr="disabled" class="ml-3 relative inline-flex items-center px-4 py-2 text-sm font-medium text-foreground bg-background border border-input leading-5 rounded-md hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:border-input transition ease-in-out duration-150 cursor-pointer">
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
                            imágenes
                        </p>
                    </div>
                    <div>
                        {{ $messages->links() }}
                    </div>
                </div>
            </div>
        </x-card.content>
    </x-card>

    <!-- Processing Modal -->
    <template x-teleport="body">
        <div x-show="showProcessingModal"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
             style="display: none;">
            <div class="bg-white rounded-lg shadow-2xl p-8 flex flex-col items-center space-y-4 max-w-sm mx-4"
                 x-transition.scale.80>
                <div class="relative">
                    <svg class="animate-spin h-16 w-16 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <p class="text-xl font-semibold text-gray-900" x-text="processingAction === 'approve' ? 'Aprobando imágenes...' : 'Rechazando imágenes...'"></p>
                <p class="text-sm text-gray-500">Por favor espera un momento</p>
            </div>
        </div>
    </template>
</div>
