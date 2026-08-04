<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MutiBrand POS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-gray-900 bg-gray-50 flex h-screen overflow-hidden"
    x-data="{ sidebarOpen: false }">

    <!-- Mobile Sidebar Overlay -->
    <div x-show="sidebarOpen" style="display: none;"
        class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm md:hidden" @click="sidebarOpen = false"
        x-transition.opacity></div>

    <!-- SIDEBAR -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 flex flex-col justify-between flex-shrink-0 transition-transform duration-300 ease-in-out md:static md:translate-x-0">
        <div>
            <!-- Logo Section -->
            <div class="h-20 flex items-center justify-between px-6 border-b border-transparent">
                <div>
                    <h1 class="text-xl font-bold text-primary">MutiBrand POS</h1>
                    <p class="text-xs text-gray-500">Flagship Store</p>
                </div>
                <!-- Tombol Tutup (Khusus Mobile) -->
                <button @click="sidebarOpen = false" class="md:hidden text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="mt-6 px-4 space-y-1">
                <!-- Dashboard (Hanya muncul jika user adalah owner) -->
                @if(auth()->check() && strtolower(auth()->user()->role ?? '') === 'owner')
                    <a href="/" wire:navigate
                        class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('/') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                            </path>
                        </svg>
                        <span class="font-medium">Dashboard</span>
                    </a>
                @endif

                <!-- POS -->
                <a href="/pos" wire:navigate
                    class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('pos*') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <span class="font-medium">POS</span>
                </a>

                <!-- Products -->
                <a href="/products" wire:navigate
                    class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('products*') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span class="font-medium">Products</span>
                </a>

                <!-- Settings -->
                <a href="/settings" wire:navigate
                    class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('settings*') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span class="font-medium">Settings</span>
                </a>
            </nav>
        </div>

        <!-- User Profile (Bottom) -->
        <div class="p-4 border-t border-gray-100">
            <div class="flex items-center p-3 bg-blue-50/50 rounded-xl">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=bfdbfe&color=1e3a8a"
                    alt="User Avatar" class="w-10 h-10 rounded-full border border-white shadow-sm">
                <div class="ml-3">
                    <p class="text-sm font-bold text-gray-900 truncate w-32">{{ auth()->user()->name ?? 'Guest User' }}
                    </p>
                    <p class="text-xs text-gray-500 font-medium uppercase">{{ auth()->user()->role ?? 'STAFF' }}</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0 bg-gray-50">

        <!-- TOP BAR -->
        <header
            class="h-20 bg-white/80 backdrop-blur-md border-b border-gray-200 flex items-center justify-between px-4 md:px-8 z-10">
            <div class="flex items-center flex-1">
                <!-- Hamburger Button (Mobile Only) -->
                <button @click="sidebarOpen = true"
                    class="mr-4 md:hidden p-2 text-gray-500 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Search Bar -->
                <div class="flex-1 max-w-2xl hidden sm:block">
                    <div class="relative flex items-center">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
                        </div>
                        <input type="text" placeholder="Scan barcode or type product name..."
                            class="w-full pl-12 pr-16 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                        <div class="absolute inset-y-0 right-2 flex items-center">
                            <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-1 rounded">ENTER</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center space-x-2 md:space-x-4 ml-4">

                <!-- OUTLET AKTIF INDICATOR -->
                @php
                    $activeOutlet = \App\Models\Outlet::find(session('current_outlet_id'));
                @endphp

                <div
                    class="hidden md:flex items-center gap-2 bg-primary/5 border border-primary/20 px-3 py-1.5 rounded-xl mr-2">
                    <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                        </path>
                    </svg>
                    <div class="text-left">
                        <p class="text-[10px] text-primary font-semibold uppercase tracking-wider leading-none">Outlet
                            Aktif</p>
                        <p class="text-xs font-bold text-gray-900 mt-0.5">
                            {{ $activeOutlet ? $activeOutlet->name : 'Belum Dipilih' }}
                        </p>
                    </div>
                </div>

                <button
                    class="sm:hidden p-2 text-gray-400 hover:text-gray-600 transition rounded-full hover:bg-gray-100">
                    <!-- Icon Search (Mobile Only) -->
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </button>
                <button class="p-2 text-gray-400 hover:text-gray-600 transition rounded-full hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                        </path>
                    </svg>
                </button>
                <button class="p-2 text-gray-400 hover:text-gray-600 transition rounded-full hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </button>
            </div>
        </header>

        <!-- PAGE CONTENT (Slot Livewire) -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            {{ $slot }}
        </main>

    </div>
</body>

</html>