<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <x-theme-script />
    <title>{{ $title ?? 'Multibrand POS' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo1.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
    $user = auth()->user();
    $isOwner = $user?->role === 'owner';
    $activeOutlet = \App\Models\Outlet::find(session('current_outlet_id'));
    $logo1 = \App\Models\Setting::get('logo_primary') ? asset('storage/' . \App\Models\Setting::get('logo_primary')) : asset('images/logo1.jpeg');
    $logo2 = \App\Models\Setting::get('logo_secondary') ? asset('storage/' . \App\Models\Setting::get('logo_secondary')) : asset('images/logo2.jpeg');
    $initials = collect(explode(' ', $user->name ?? 'U'))->take(2)->map(fn($w) => strtoupper(mb_substr($w, 0, 1)))->implode('');

    // [url, label, ikon, pola aktif, khusus owner]
    $menu = [
        'Menu' => [
            ['/dashboard', 'Dashboard', 'dashboard', 'dashboard', true],
            ['/pos', 'Kasir (POS)', 'pos', 'pos*', false],
            ['/products', 'Produk', 'box', 'products*', false],
            ['/stocks/history', 'Stok', 'clipboard', 'stocks*', false],
            ['/categories-brands', 'Kategori & Brand', 'tag', 'categories-brands*', false],
        ],
        'Owner' => [
            ['/transactions', 'Transaksi', 'receipt', 'transactions*', true],
            ['/reports', 'Laporan', 'chart', 'reports*', true],
            ['/monitoring', 'Monitoring', 'eye', 'monitoring*', true],
            ['/staff', 'Karyawan', 'users', 'staff*', true],
            ['/outlets', 'Outlet', 'store', 'outlets*', true],
            ['/settings', 'Pengaturan', 'cog', 'settings*', true],
        ],
    ];
    $isActive = fn($pattern) => request()->is($pattern) && !(request()->is('products/top') && $pattern === 'products*');

    // Navigasi bawah (HP): menu yang paling sering dipakai
    $bottomNav = array_values(array_filter([
        ['/pos', 'Kasir', 'pos', 'pos*', false],
        ['/products', 'Produk', 'box', 'products*', false],
        ['/stocks/history', 'Stok', 'clipboard', 'stocks*', false],
        $isOwner ? ['/dashboard', 'Dashboard', 'dashboard', 'dashboard', true] : ['/profile', 'Profil', 'user', 'profile*', false],
    ]));
@endphp

<body class="min-h-screen" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

    <!-- Overlay drawer (HP & tablet) -->
    <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        @click="sidebarOpen = false"></div>

    <div class="lg:flex lg:gap-4 lg:p-4 min-h-screen">
        <!-- ===== Sidebar ===== -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 w-[17.5rem] p-3 -translate-x-full transition-transform duration-300 ease-out lg:static lg:z-auto lg:w-64 lg:p-0 lg:translate-x-0 flex-shrink-0">
            <div class="h-full lg:h-[calc(100vh-2rem)] lg:sticky lg:top-4 bg-white rounded-3xl border border-zinc-200/80 shadow-tile flex flex-col overflow-hidden">
                <!-- Brand -->
                <div class="p-4 pb-3 flex items-center justify-between gap-2">
                    <a href="{{ $isOwner ? '/dashboard' : '/pos' }}" wire:navigate class="flex items-center gap-3 min-w-0">
                        <div class="flex -space-x-2 flex-shrink-0">
                            <img src="{{ $logo1 }}" alt="Logo 1" class="w-10 h-10 rounded-full object-cover ring-2 ring-white bg-white border border-zinc-200">
                            <img src="{{ $logo2 }}" alt="Logo 2" class="w-10 h-10 rounded-full object-cover ring-2 ring-white bg-white border border-zinc-200">
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold leading-tight truncate">Multibrand</p>
                            <p class="text-[11px] text-zinc-400 leading-tight">Point of Sale</p>
                        </div>
                    </a>
                    <button type="button" @click="sidebarOpen = false" class="btn-icon lg:hidden" aria-label="Tutup menu">
                        <x-icon name="x" />
                    </button>
                </div>

                <!-- Outlet aktif -->
                <div class="mx-4 mb-2 rounded-2xl bg-ink text-white px-3.5 py-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                        <x-icon name="store" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-white/50 font-semibold">Outlet aktif</p>
                        <p class="text-sm font-semibold truncate">{{ $activeOutlet->name ?? 'Belum dipilih' }}</p>
                    </div>
                </div>

                <!-- Navigasi -->
                <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
                    @foreach ($menu as $group => $items)
                        @php $items = array_filter($items, fn($i) => !$i[4] || $isOwner); @endphp
                        @if ($items && $group !== 'Menu')
                            <p class="px-3.5 pt-4 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-zinc-400">{{ $group }}</p>
                        @endif
                        @foreach ($items as [$url, $label, $icon, $pattern])
                            <a href="{{ $url }}" wire:navigate class="{{ $isActive($pattern) ? 'nav-link-active' : 'nav-link' }}">
                                <x-icon :name="$icon" class="w-[18px] h-[18px]" />
                                <span>{{ $label }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </nav>

                <!-- Profil -->
                <div class="p-3 border-t border-zinc-100">
                    <a href="/profile" wire:navigate class="flex items-center gap-3 rounded-2xl p-2.5 hover:bg-zinc-100 transition {{ request()->is('profile') ? 'bg-zinc-100' : '' }}">
                        <div class="w-9 h-9 rounded-full bg-ink text-white flex items-center justify-center text-xs font-bold flex-shrink-0">{{ $initials }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold truncate">{{ $user->name ?? 'Guest' }}</p>
                            <p class="text-[11px] text-zinc-400 capitalize">{{ $user->role ?? 'staff' }}</p>
                        </div>
                        <x-icon name="chevron-right" class="w-4 h-4 text-zinc-300" />
                    </a>
                </div>
            </div>
        </aside>

        <!-- ===== Konten ===== -->
        <div class="flex-1 min-w-0 flex flex-col">
            <!-- Topbar -->
            <header class="sticky top-0 z-30 px-3 pt-3 lg:static lg:p-0">
                <div class="bg-white/90 backdrop-blur rounded-3xl border border-zinc-200/80 shadow-tile px-3 py-2.5 md:px-5 md:py-3 flex items-center gap-3">
                    <button type="button" @click="sidebarOpen = true" class="btn-icon lg:hidden" aria-label="Buka menu">
                        <x-icon name="menu" />
                    </button>

                    <div class="min-w-0 flex-1" x-data="{
                        time: '', greeting: '', timer: null,
                        tick() {
                            const now = new Date();
                            this.time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' · ' + now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short' });
                            const h = now.getHours();
                            this.greeting = h < 11 ? 'Selamat pagi' : (h < 15 ? 'Selamat siang' : (h < 18 ? 'Selamat sore' : 'Selamat malam'));
                        },
                        init() { this.tick(); this.timer = setInterval(() => this.tick(), 30000); },
                        destroy() { clearInterval(this.timer); },
                    }">
                        <p class="text-sm font-semibold truncate" x-text="greeting + ', {{ e(explode(' ', $user->name ?? 'User')[0]) }}'"></p>
                        <p class="text-xs text-zinc-400 truncate" x-text="time"></p>
                    </div>

                    <x-theme-toggle />

                    <div class="hidden md:flex items-center gap-2 rounded-2xl border border-zinc-200 px-3 py-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-xs font-semibold truncate max-w-[10rem]">{{ $activeOutlet->name ?? 'Belum dipilih' }}</span>
                    </div>

                    <a href="/profile" wire:navigate class="w-9 h-9 rounded-full bg-ink text-white flex items-center justify-center text-xs font-bold flex-shrink-0" title="Profil">
                        {{ $initials }}
                    </a>
                </div>
            </header>

            <main class="flex-1 px-3 pt-3 pb-28 md:pt-4 lg:px-0 lg:pb-4">
                {{ $slot }}
            </main>
        </div>
    </div>

    <!-- Navigasi bawah (HP & tablet) -->
    <nav class="fixed bottom-0 inset-x-0 z-30 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
        <div class="bg-ink text-white rounded-3xl shadow-lift px-2 py-2 grid grid-cols-5 gap-1">
            @foreach ($bottomNav as [$url, $label, $icon, $pattern])
                <a href="{{ $url }}" wire:navigate
                    class="flex flex-col items-center gap-0.5 rounded-2xl py-1.5 text-[10px] font-medium transition {{ $isActive($pattern) ? 'bg-white text-ink' : 'text-white/60 hover:text-white' }}">
                    <x-icon :name="$icon" class="w-5 h-5" />
                    {{ $label }}
                </a>
            @endforeach
            <button type="button" @click="sidebarOpen = true" class="flex flex-col items-center gap-0.5 rounded-2xl py-1.5 text-[10px] font-medium text-white/60 hover:text-white">
                <x-icon name="menu" class="w-5 h-5" />
                Menu
            </button>
        </div>
    </nav>

    @if (session('notify'))
        <script>
            (() => {
                const show = () => window.notify(@js(session('notify')));
                window.notify ? show() : document.addEventListener('DOMContentLoaded', show);
            })();
        </script>
    @endif
</body>

</html>
