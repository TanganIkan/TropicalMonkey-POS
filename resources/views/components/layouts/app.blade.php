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
            <div class="h-20 flex items-center justify-between px-6 border-b border-transparent">

                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo1.jpeg') }}" alt="Brand 1"
                        class="w-10 h-10 md:w-12 md:h-12 rounded-full object-cover bg-white border border-gray-200 shadow-sm flex-shrink-0">

                    <span class="text-gray-300 font-black text-xs uppercase tracking-widest">X</span>

                    <img src="{{ asset('images/logo2.jpeg') }}" alt="Brand 2"
                        class="w-10 h-10 md:w-12 md:h-12 rounded-full object-cover bg-white border border-gray-200 shadow-sm flex-shrink-0">
                </div>

                <!-- Tombol Tutup (Khusus Mobile) -->
                <button @click="sidebarOpen = false"
                    class="md:hidden text-gray-400 hover:text-gray-900 transition-all duration-300 ease-in-out p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="mt-6 px-4 space-y-1">
                @if (auth()->check() && strtolower(auth()->user()->role ?? '') === 'owner')
                    <a href="/dashboard" wire:navigate
                        class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('dashboard') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                            </path>
                        </svg>
                        <span class="font-medium">Dashboard</span>
                    </a>
                @endif

                @if (auth()->check() && strtolower(auth()->user()->role ?? '') === 'owner')
                    <a href="/staff" wire:navigate
                        class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('staff') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                            </path>
                        </svg>
                        <span class="font-medium">Karyawan</span>
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
                    <span class="font-medium">Manajemen Produk</span>
                </a>

                <!-- Categories & Brands -->
                <a href="/categories-brands" wire:navigate
                    class="flex items-center px-4 py-3 rounded-lg transition {{ request()->is('categories-brands*') ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                        </path>
                    </svg>
                    <span class="font-medium">Kategori & Brand</span>
                </a>
            </nav>
        </div>

        <!-- User Profile (Bottom) -->
        <div class="p-4 border-t border-gray-100">
            <a href="/profile" wire:navigate
                class="flex items-center p-3 bg-gray-50 rounded-xl hover:bg-gray-100 border border-transparent hover:border-gray-200 transition-all duration-300 ease-in-out cursor-pointer group">

                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'User') }}&background=111827&color=ffffff&bold=true"
                    alt="User Avatar"
                    class="w-10 h-10 rounded-full border-2 border-white shadow-sm flex-shrink-0 group-hover:scale-105 transition-transform duration-300 ease-in-out">

                <div class="ml-3 flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-900 truncate">{{ auth()->user()->name ?? 'Guest User' }}</p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-0.5">
                        {{ auth()->user()->role ?? 'STAFF' }}
                    </p>
                </div>

            </a>
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

                <div class="hidden sm:flex flex-col" x-data="{ time: '', greeting: '' }" x-init="const updateClock = () => {
                    const now = new Date();
                    time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                
                    const hour = now.getHours();
                    greeting = hour < 11 ? 'Selamat pagi' : (hour < 15 ? 'Selamat siang' : (hour < 18 ? 'Selamat sore' : 'Selamat malam'));
                };
                updateClock();
                setInterval(updateClock, 1000);">
                    <p class="text-sm font-bold text-gray-800"
                        x-text="greeting + ', {{ explode(' ', auth()->user()->name ?? 'User')[0] }} 👋'"></p>
                    <p class="text-xs text-gray-400" x-text="time"></p>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center space-x-2 md:space-x-4 ml-4">

                @php
                    $activeOutlet = \App\Models\Outlet::find(session('current_outlet_id'));
                @endphp

                <div
                    class="flex items-center gap-1.5 md:gap-2 bg-primary/5 border border-primary/20 px-2 md:px-3 py-1.5 rounded-xl mr-1 md:mr-2">
                    <svg class="w-3.5 h-3.5 md:w-4 md:h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                        </path>
                    </svg>
                    <div class="text-left">
                        <p
                            class="text-[8px] md:text-[10px] text-primary font-semibold uppercase tracking-wider leading-none">
                            Outlet Aktif</p>
                        <p
                            class="text-[10px] md:text-xs font-bold text-gray-900 mt-0.5 truncate max-w-[70px] md:max-w-none">
                            {{ $activeOutlet ? $activeOutlet->name : 'Belum Dipilih' }}
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT (Slot Livewire) -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            {{ $slot }}
        </main>

    </div>
</body>

</html>