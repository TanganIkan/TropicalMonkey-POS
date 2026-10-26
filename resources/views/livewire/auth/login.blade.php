<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;
use App\Models\Outlet;
use App\Services\LoginService;

new #[Layout('components.layouts.guest')] class extends Component {
    public $username = '';
    public $password = '';
    public $remember = false;

    // Properti untuk multi-outlet
    public $step = 1;
    public $outlets = [];
    public $selected_outlet_id = '';
    public $pendingRole = '';

    public function authenticate(LoginService $login)
    {
        $this->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = $login->verifyCredentials($this->username, $this->password, request()->ip());

        $this->reset('password');
        $this->pendingRole = $user->role;
        $this->step = 2;
        $this->outlets = Outlet::where('is_active', true)->get();
        if ($this->outlets->count() > 0) {
            $this->selected_outlet_id = $this->outlets->first()->id;
        }
    }

    public function enterPos(LoginService $login)
    {
        $this->validate([
            'selected_outlet_id' => ['required', Rule::exists('outlets', 'id')->where('is_active', true)],
        ]);

        if (!$login->pendingUser()) {
            $this->step = 1;
            $this->addError('username', 'Sesi login sudah kedaluwarsa. Silakan masukkan username dan password lagi.');
            return;
        }

        $user = $login->complete((int) $this->selected_outlet_id, (bool) $this->remember);

        return redirect($user->role === 'owner' ? '/dashboard' : '/pos');
    }
};
?>

@php
    $logo1 = \App\Models\Setting::get('logo_primary') ? asset('storage/' . \App\Models\Setting::get('logo_primary')) : asset('images/logo1.jpeg');
    $logo2 = \App\Models\Setting::get('logo_secondary') ? asset('storage/' . \App\Models\Setting::get('logo_secondary')) : asset('images/logo2.jpeg');
@endphp

<div class="min-h-screen p-3 md:p-4 lg:p-6 flex">
    <div class="bento w-full grid-cols-1 lg:grid-cols-5 lg:grid-rows-[1fr_auto] max-w-6xl mx-auto">

        <!-- Tile branding -->
        <div class="tile-dark relative overflow-hidden lg:col-span-3 lg:row-span-2 flex flex-col justify-between min-h-[12rem] lg:min-h-0 p-6 md:p-10">
            <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full border border-white/10"></div>
            <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full border border-white/10"></div>

            <div class="relative flex items-center gap-3">
                <div class="flex -space-x-2">
                    <img src="{{ $logo1 }}" alt="Logo 1" class="w-11 h-11 md:w-12 md:h-12 rounded-full object-cover ring-2 ring-ink bg-white">
                    <img src="{{ $logo2 }}" alt="Logo 2" class="w-11 h-11 md:w-12 md:h-12 rounded-full object-cover ring-2 ring-ink bg-white">
                </div>
                <span class="text-sm font-semibold text-white/70">Multibrand POS</span>
            </div>

            <div class="relative mt-8 lg:mt-0">
                <h1 class="text-3xl md:text-5xl font-bold tracking-tight leading-[1.1]">Kelola semua<br class="hidden md:block"> brand dalam<br class="hidden md:block"> satu kasir.</h1>
                <p class="hidden md:block text-white/60 mt-4 max-w-md text-sm">Penjualan, stok, dan laporan setiap outlet — tersinkron dalam satu sistem.</p>
            </div>

            <div class="relative hidden lg:grid grid-cols-3 gap-3 mt-10">
                @foreach ([['pos', 'Kasir cepat'], ['clipboard', 'Stok real-time'], ['chart', 'Laporan lengkap']] as [$icon, $label])
                    <div class="rounded-2xl bg-white/5 border border-white/10 p-4">
                        <x-icon :name="$icon" class="w-5 h-5 text-white/80" />
                        <p class="text-xs font-medium text-white/70 mt-3">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Tile form -->
        <div class="tile lg:col-span-2 flex flex-col justify-center p-6 md:p-10">
            @if ($step == 1)
                <div class="mb-8">
                    <div class="w-12 h-12 rounded-2xl bg-ink text-white flex items-center justify-center mb-5">
                        <x-icon name="lock" />
                    </div>
                    <h2 class="text-2xl font-bold tracking-tight">Masuk</h2>
                    <p class="text-sm text-zinc-500 mt-1">Gunakan akun kasir atau owner Anda.</p>
                </div>

                <form wire:submit="authenticate" class="space-y-4">
                    @if (session('status'))
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-medium px-4 py-3">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="rounded-2xl bg-red-50 border border-red-100 text-red-700 text-xs font-medium px-4 py-3">{{ session('error') }}</div>
                    @endif

                    <div>
                        <label class="label" for="username">Username</label>
                        <input id="username" type="text" wire:model="username" placeholder="mis. elga" autofocus autocomplete="username" class="input-lg">
                        @error('username') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div x-data="{ show: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="label !mb-0" for="password">Password</label>
                            <button type="button" onclick="showForgotPassword()" class="text-xs font-semibold text-zinc-500 hover:text-zinc-900">Lupa password?</button>
                        </div>
                        <div class="relative">
                            <input id="password" :type="show ? 'text' : 'password'" wire:model="password" placeholder="••••••••" autocomplete="current-password" class="input-lg pr-12">
                            <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 btn-icon" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                                <x-icon name="eye" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary btn-lg w-full mt-2">
                        <span wire:loading.remove wire:target="authenticate">Lanjutkan</span>
                        <span wire:loading wire:target="authenticate">Memeriksa...</span>
                        <x-icon name="arrow-right" class="w-4 h-4" wire:loading.remove wire:target="authenticate" />
                    </button>
                </form>
            @else
                <div class="mb-6">
                    <div class="w-12 h-12 rounded-2xl bg-ink text-white flex items-center justify-center mb-5">
                        <x-icon name="store" />
                    </div>
                    <h2 class="text-2xl font-bold tracking-tight">Pilih Outlet</h2>
                    <p class="text-sm text-zinc-500 mt-1">Tentukan lokasi tempat Anda bertugas saat ini.</p>
                </div>

                <form wire:submit="enterPos" class="space-y-5">
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($outlets as $outlet)
                            <button type="button" wire:click="$set('selected_outlet_id', {{ $outlet->id }})"
                                class="rounded-2xl border-2 p-4 text-left transition {{ $selected_outlet_id == $outlet->id ? 'border-ink bg-ink text-white' : 'border-zinc-200 hover:border-zinc-400' }}">
                                <x-icon name="store" class="w-5 h-5 {{ $selected_outlet_id == $outlet->id ? 'text-white' : 'text-zinc-400' }}" />
                                <p class="text-sm font-semibold mt-3 leading-tight">{{ $outlet->name }}</p>
                            </button>
                        @endforeach
                    </div>
                    @error('selected_outlet_id') <span class="field-error">{{ $message }}</span> @enderror

                    <button type="submit" class="btn-primary btn-lg w-full">
                        <span wire:loading.remove wire:target="enterPos">{{ $pendingRole === 'owner' ? 'Masuk ke Dashboard' : 'Mulai Sesi Kasir' }}</span>
                        <span wire:loading wire:target="enterPos">Menyiapkan...</span>
                        <x-icon name="arrow-right" class="w-4 h-4" wire:loading.remove wire:target="enterPos" />
                    </button>
                </form>
            @endif
        </div>

        <!-- Tile kecil info -->
        <div class="tile lg:col-span-2 flex items-center gap-3 !py-4">
            <div class="w-9 h-9 rounded-xl bg-zinc-100 flex items-center justify-center flex-shrink-0"><x-icon name="info" class="w-4 h-4" /></div>
            <p class="text-xs text-zinc-500">Shift kasir dimulai otomatis setelah login dan ditutup saat logout.</p>
        </div>
    </div>
</div>
