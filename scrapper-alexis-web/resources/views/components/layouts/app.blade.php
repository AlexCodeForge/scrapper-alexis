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
                            <a href="{{ route('posting.approve') }}"
                               wire:navigate
                               wire:navigate.hover
                                class="@if(request()->routeIs('posting.approve')) border-primary text-foreground bg-accent @else border-transparent text-muted-foreground hover:border-border hover:text-foreground @endif inline-flex items-center px-4 py-2 border-b-2 text-sm lg:text-base font-medium transition-colors">
                                <x-lucide-check-circle class="h-4 w-4 lg:h-5 lg:w-5 mr-2" />
                                Aprobación
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
                    <a href="{{ route('posting.approve') }}"
                       wire:navigate
                        class="@if(request()->routeIs('posting.approve')) bg-accent border-primary text-foreground @else border-transparent text-muted-foreground hover:bg-accent/50 hover:border-border hover:text-foreground @endif flex items-center gap-2 pl-3 pr-4 py-3 border-l-4 text-base font-medium transition-colors">
                        <x-lucide-check-circle class="h-5 w-5" />
                        Aprobación
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

    @livewireScripts
</body>
</html>

