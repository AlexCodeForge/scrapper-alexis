<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin Scraper' }}</title>
    @vite(['resources/css/luvi-ui.css', 'resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-background text-foreground">
    <div class="min-h-screen" x-data="{ mobileMenuOpen: false }">
        <!-- Navigation -->
        <nav class="bg-card border-b border-border shadow-sm">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center gap-3">
                            <x-lucide-layout-dashboard class="h-7 w-7 text-primary" />
                            <h1 class="text-xl lg:text-2xl font-bold text-foreground">Admin Scraper</h1>
                        </div>
                        <div class="max-sm:hidden sm:flex sm:ml-8 lg:ml-12 space-x-2">
                            <a href="{{ route('dashboard') }}"
                               wire:navigate
                               wire:navigate.hover
                                class="@if(request()->routeIs('dashboard')) border-primary text-foreground bg-accent @else border-transparent text-muted-foreground hover:border-border hover:text-foreground @endif inline-flex items-center px-4 py-2 border-b-2 text-sm lg:text-base font-medium transition-colors">
                                <x-lucide-home class="h-4 w-4 lg:h-5 lg:w-5 mr-2" />
                                Panel
                            </a>
                            <a href="{{ route('images') }}"
                               wire:navigate
                               wire:navigate.hover
                                class="@if(request()->routeIs('images')) border-primary text-foreground bg-accent @else border-transparent text-muted-foreground hover:border-border hover:text-foreground @endif inline-flex items-center px-4 py-2 border-b-2 text-sm lg:text-base font-medium transition-colors">
                                <x-lucide-image class="h-4 w-4 lg:h-5 lg:w-5 mr-2" />
                                Imágenes
                            </a>
                            <a href="{{ route('messages') }}"
                               wire:navigate
                               wire:navigate.hover
                                class="@if(request()->routeIs('messages')) border-primary text-foreground bg-accent @else border-transparent text-muted-foreground hover:border-border hover:text-foreground @endif inline-flex items-center px-4 py-2 border-b-2 text-sm lg:text-base font-medium transition-colors">
                                <x-lucide-message-square class="h-4 w-4 lg:h-5 lg:w-5 mr-2" />
                                Mensajes
                            </a>
                            <a href="{{ route('logs') }}"
                               wire:navigate
                               wire:navigate.hover
                                class="@if(request()->routeIs('logs')) border-primary text-foreground bg-accent @else border-transparent text-muted-foreground hover:border-border hover:text-foreground @endif inline-flex items-center px-4 py-2 border-b-2 text-sm lg:text-base font-medium transition-colors">
                                <x-lucide-file-text class="h-4 w-4 lg:h-5 lg:w-5 mr-2" />
                                Logs
                            </a>
                            <a href="{{ route('settings') }}"
                               wire:navigate
                               wire:navigate.hover
                                class="@if(request()->routeIs('settings')) border-primary text-foreground bg-accent @else border-transparent text-muted-foreground hover:border-border hover:text-foreground @endif inline-flex items-center px-4 py-2 border-b-2 text-sm lg:text-base font-medium transition-colors">
                                <x-lucide-settings class="h-4 w-4 lg:h-5 lg:w-5 mr-2" />
                                Configuración
                            </a>
                        </div>
                    </div>
                    <div class="max-sm:hidden sm:flex sm:ml-6 items-center gap-2">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="ghost" size="sm">
                                <x-lucide-log-out class="h-4 w-4 mr-2" />
                                Cerrar Sesión
                            </x-button>
                        </form>
                    </div>
                    <!-- Mobile menu button -->
                    <div class="max-sm:flex sm:hidden -mr-2 items-center">
                        <x-button
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <x-lucide-menu class="h-5 w-5" x-show="!mobileMenuOpen" />
                            <x-lucide-x class="h-5 w-5" x-show="mobileMenuOpen" x-cloak />
                        </x-button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <div x-show="mobileMenuOpen"
                 x-collapse
                 class="sm:hidden border-t border-border"
                 x-cloak>
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <a href="{{ route('dashboard') }}"
                       wire:navigate
                        class="@if(request()->routeIs('dashboard')) bg-accent border-primary text-foreground @else border-transparent text-muted-foreground hover:bg-accent/50 hover:border-border hover:text-foreground @endif flex items-center gap-2 pl-3 pr-4 py-3 border-l-4 text-base font-medium transition-colors">
                        <x-lucide-home class="h-5 w-5" />
                        Panel
                    </a>
                    <a href="{{ route('images') }}"
                       wire:navigate
                        class="@if(request()->routeIs('images')) bg-accent border-primary text-foreground @else border-transparent text-muted-foreground hover:bg-accent/50 hover:border-border hover:text-foreground @endif flex items-center gap-2 pl-3 pr-4 py-3 border-l-4 text-base font-medium transition-colors">
                        <x-lucide-image class="h-5 w-5" />
                        Imágenes
                    </a>
                    <a href="{{ route('messages') }}"
                       wire:navigate
                        class="@if(request()->routeIs('messages')) bg-accent border-primary text-foreground @else border-transparent text-muted-foreground hover:bg-accent/50 hover:border-border hover:text-foreground @endif flex items-center gap-2 pl-3 pr-4 py-3 border-l-4 text-base font-medium transition-colors">
                        <x-lucide-message-square class="h-5 w-5" />
                        Mensajes
                    </a>
                    <a href="{{ route('logs') }}"
                       wire:navigate
                        class="@if(request()->routeIs('logs')) bg-accent border-primary text-foreground @else border-transparent text-muted-foreground hover:bg-accent/50 hover:border-border hover:text-foreground @endif flex items-center gap-2 pl-3 pr-4 py-3 border-l-4 text-base font-medium transition-colors">
                        <x-lucide-file-text class="h-5 w-5" />
                        Logs
                    </a>
                    <a href="{{ route('settings') }}"
                       wire:navigate
                        class="@if(request()->routeIs('settings')) bg-accent border-primary text-foreground @else border-transparent text-muted-foreground hover:bg-accent/50 hover:border-border hover:text-foreground @endif flex items-center gap-2 pl-3 pr-4 py-3 border-l-4 text-base font-medium transition-colors">
                        <x-lucide-settings class="h-5 w-5" />
                        Configuración
                    </a>
                    <x-separator class="my-2" />
                    <div class="px-3 py-2">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="outline" class="w-full">
                                <x-lucide-log-out class="h-4 w-4 mr-2" />
                                Cerrar Sesión
                            </x-button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-8 lg:py-12">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    <!-- Global Toast Notifications -->
    <div wire:ignore
         x-data="{
        showToast: false,
        toastType: 'success',
        toastMessage: '',
        init() {
            console.log('Toast: Global component initialized');
        }
    }"
         @settings-saved.window="
            console.log('Toast: Settings saved event received', $event.detail);
            toastType = 'success';
            toastMessage = $event.detail.message;
            showToast = true;
            setTimeout(() => {
                showToast = false;
                console.log('Toast: Auto-dismissed');
            }, 5000);
         "
         @settings-error.window="
            console.log('Toast: Settings error event received', $event.detail);
            toastType = 'error';
            toastMessage = $event.detail.message;
            showToast = true;
            setTimeout(() => {
                showToast = false;
                console.log('Toast: Auto-dismissed');
            }, 5000);
         ">
        <!-- Success/Error Toast -->
        <div x-show="showToast"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-x-full"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform translate-x-full"
             style="z-index: 9999;"
             class="fixed top-4 right-4 max-w-md shadow-2xl">
            <div x-bind:class="toastType === 'success' ? 'border-2 border-green-500 bg-green-50' : 'border-2 border-red-500 bg-red-50'"
                 class="rounded-lg p-4 pr-10 shadow-lg relative">
                <div class="flex items-start gap-3">
                    <template x-if="toastType === 'success'">
                        <svg class="h-5 w-5 text-green-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </template>
                    <template x-if="toastType === 'error'">
                        <svg class="h-5 w-5 text-red-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </template>
                    <div class="flex-1">
                        <h5 x-bind:class="toastType === 'success' ? 'text-green-800' : 'text-red-800'"
                            class="font-semibold mb-1"
                            x-text="toastType === 'success' ? 'Éxito' : 'Error'"></h5>
                        <p x-bind:class="toastType === 'success' ? 'text-green-700' : 'text-red-700'"
                           class="text-sm"
                           x-text="toastMessage"></p>
                    </div>
                </div>
                <button @click="showToast = false; console.log('Toast: Manually closed');"
                        x-bind:class="toastType === 'success' ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800'"
                        class="absolute top-3 right-3 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>

