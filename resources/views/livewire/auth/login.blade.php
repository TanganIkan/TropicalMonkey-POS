<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Models\Outlet;

new #[Layout('components.layouts.guest')] class extends Component {
    public $username = '';
    public $password = '';
    public $remember = false;

    // Properti untuk multi-outlet
    public $step = 1;
    public $outlets = [];
    public $selected_outlet_id = '';

    public function authenticate()
    {
        $this->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt(['username' => $this->username, 'password' => $this->password], $this->remember)) {
            $this->step = 2;
            $this->outlets = Outlet::where('is_active', true)->get();
            if ($this->outlets->count() > 0) {
                $this->selected_outlet_id = $this->outlets->first()->id;
            }
        } else {
            $this->addError('username', 'Username atau Password tidak valid.');
        }
    }

    public function enterPos()
    {
        $this->validate([
            'selected_outlet_id' => 'required|exists:outlets,id',
        ]);

        session(['current_outlet_id' => $this->selected_outlet_id]);

        if (Auth::user()->role === 'owner') {
            return redirect('/dashboard');
        } else {
            return redirect('/pos');
        }
    }
};
?>

<div>
    <div class="min-h-screen flex items-center justify-centerp-4 relative overflow-hidden">

        <div
            class="absolute top-[-10%] left-[-10%] w-96 h-96 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob">
        </div>
        <div
            class="absolute bottom-[-10%] right-[-10%] w-96 h-96 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-2000">
        </div>

        <div class="max-w-md w-full bg-white rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8 sm:p-10 relative z-10"
            x-data="{ step: @entangle('step') }">

            @if($step == 1)
                <div class="text-center mb-10">
                    <div
                        class="w-16 h-16 bg-gray-900 text-white rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-gray-900/20 transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">Multibrand POS</h2>
                    <p class="text-sm font-medium text-gray-500 mt-2">Masuk ke sistem kasir Anda.</p>
                </div>

                <form wire:submit="authenticate" x-transition:enter="transition-all ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="mb-5">
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Username</label>
                        <input type="text" wire:model="username" placeholder="Masukkan username..." autofocus
                            class="w-full px-4 py-3.5 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300 outline-none font-medium text-gray-900">
                        @error('username') <span class="text-red-500 text-xs mt-1.5 font-bold block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="block text-sm font-bold text-gray-700">Password</label>

                            <!-- Tombol Lupa Password menggunakan SweetAlert -->
                            <button type="button" x-data @click="
                                                                                                                Swal.fire({
                                                                                                                    title: 'Lupa Password?',
                                                                                                                    text: 'Silakan hubungi Owner atau Administrator toko untuk melakukan reset password akun Anda.',
                                                                                                                    icon: 'info',
                                                                                                                    confirmButtonColor: '#111827',
                                                                                                                    confirmButtonText: 'Mengerti',
                                                                                                                    heightAuto: false,
                                                                                                                    scrollbarPadding: false
                                                                                                                })
                                                                                                            "
                                class="text-xs font-bold text-gray-500 hover:text-gray-900 hover:underline transition-all">
                                Lupa Password?
                            </button>
                        </div>
                        <input type="password" wire:model="password" placeholder="••••••••"
                            class="w-full px-4 py-3.5 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300 outline-none font-medium text-gray-900">
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                        class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl hover:bg-black transition-all duration-300 flex justify-center items-center gap-2 shadow-lg shadow-gray-900/30">
                        <span wire:loading.remove wire:target="authenticate">Masuk Ke Akun</span>
                        <span wire:loading wire:target="authenticate">Memeriksa...</span>
                        <svg wire:loading.remove wire:target="authenticate" class="w-5 h-5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>

            @else
                <form wire:submit="enterPos" x-transition:enter="transition-all ease-out duration-500"
                    x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">

                    <div class="mb-8 text-center">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-900 mb-5 shadow-inner border border-gray-200">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-gray-900">Pilih Outlet</h3>
                        <p class="text-sm font-medium text-gray-500 mt-2">Tentukan lokasi tempat Anda bertugas saat ini.</p>
                    </div>

                    <div class="mb-10">
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($outlets as $outlet)
                                <button type="button" wire:click="$set('selected_outlet_id', {{ $outlet->id }})"
                                    class="py-5 px-3 rounded-2xl border-2 font-bold flex flex-col items-center justify-center gap-3 transition-all duration-300 ease-in-out shadow-sm
                                                                                                                                                                                                    {{ $selected_outlet_id == $outlet->id ? 'border-gray-900 text-gray-900 bg-gray-900/5 ring-4 ring-gray-900/10' : 'border-gray-100 text-gray-500 hover:border-gray-300 hover:bg-gray-50' }}">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V10l-7-5-7 5v11a2 2 0 002 2h10a2 2 0 002-2zM12 11v4M10 15h4"></path>
                                    </svg>
                                    <span class="text-sm text-center leading-tight">{{ $outlet->name }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('selected_outlet_id')
                            <span class="text-red-500 text-xs mt-3 block text-center font-bold">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit"
                        class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl hover:bg-black transition-all duration-300 shadow-lg shadow-gray-900/30 flex justify-center items-center gap-2">
                        <span wire:loading.remove wire:target="enterPos">
                            {{ Auth::user()->role === 'owner' ? 'Masuk ke Dashboard' : 'Mulai Sesi Kasir' }}
                        </span>
                        <span wire:loading wire:target="enterPos">Menyiapkan Sistem...</span>
                        <svg wire:loading.remove wire:target="enterPos" class="w-5 h-5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>
            @endif

        </div>
    </div>
</div>