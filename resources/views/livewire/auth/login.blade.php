<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Models\Outlet;

new #[Layout('components.layouts.guest')] class extends Component {
    public $role = 'staff';
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

        if (Auth::attempt(['username' => $this->username, 'password' => $this->password, 'role' => $this->role], $this->remember)) {
            $user = Auth::user();

            if ($user->role === 'owner') {
                return redirect()->intended('/dashboard');
            } else {
                $this->step = 2;
                $this->outlets = Outlet::where('is_active', true)->get();
                if ($this->outlets->count() > 0) {
                    $this->selected_outlet_id = $this->outlets->first()->id;
                }
            }
        } else {
            $this->addError('username', 'Kredensial tidak valid atau Role salah.');
        }
    }

    public function enterPos()
    {
        $this->validate([
            'selected_outlet_id' => 'required|exists:outlets,id',
        ]);

        session(['current_outlet_id' => $this->selected_outlet_id]);

        return redirect()->intended('/pos');
    }
};
?>

<div>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-gray-100 p-8">

            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Multibrand POS</h2>
                <p class="text-sm text-gray-500 mt-2">Efisiensi yang mulus demi keunggulan ritel modern.</p>
            </div>

            @if($step == 1)
                <form wire:submit="authenticate">
                    <!-- Pilihan Role (Staff / Owner) -->
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-3">Login Role</label>
                        <div class="flex space-x-4">
                            <button type="button" wire:click="$set('role', 'staff')"
                                class="flex-1 py-3 rounded-lg border-2 font-medium flex items-center justify-center gap-2 transition 
                                                                                        {{ $role === 'staff' ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2">
                                    </path>
                                </svg>
                                Staff
                            </button>
                            <button type="button" wire:click="$set('role', 'owner')"
                                class="flex-1 py-3 rounded-lg border-2 font-medium flex items-center justify-center gap-2 transition 
                                                                                        {{ $role === 'owner' ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                    </path>
                                </svg>
                                Owner
                            </button>
                        </div>
                    </div>

                    <!-- Username -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" wire:model="username" placeholder="e.g. alex_v"
                            class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-primary focus:border-primary transition outline-none">
                        @error('username') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-6">
                        <div class="flex justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            <a href="#" class="text-sm text-primary hover:underline">Lupa Password?</a>
                        </div>
                        <input type="password" wire:model="password" placeholder="••••••••"
                            class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 focus:bg-white focus:ring-2 focus:ring-primary focus:border-primary transition outline-none">
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                        class="w-full bg-primary text-white font-medium py-3 rounded-lg hover:bg-primary/90 transition flex justify-center items-center gap-2">
                        Masuk Ke POS
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </form>

            @else
                <!-- STEP 2: Pemilihan Outlet Khusus Kasir -->
                <form wire:submit="enterPos">
                    <div class="mb-6 text-center">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-500 mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Kredensial Diterima!</h3>
                        <p class="text-sm text-gray-500 mt-1">Silakan pilih outlet tempat Anda bertugas hari ini.</p>
                    </div>

                    <div class="mb-8">
                        <div class="flex space-x-4">
                            @foreach($outlets as $outlet)
                                <button type="button" wire:click="$set('selected_outlet_id', {{ $outlet->id }})"
                                    class="flex-1 py-4 px-2 rounded-xl border-2 font-medium flex flex-col items-center justify-center gap-2 transition 
                                                                                                                                                    {{ $selected_outlet_id == $outlet->id ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500 hover:border-gray-300' }}">
                                    <!-- Ikon Toko/Storefront -->
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V10l-7-5-7 5v11a2 2 0 002 2h10a2 2 0 002-2zM12 11v4M10 15h4"></path>
                                    </svg>
                                    <span class="text-sm text-center leading-tight">{{ $outlet->name }}</span>
                                </button>
                            @endforeach
                        </div>
                        @error('selected_outlet_id') <span
                        class="text-red-500 text-xs mt-2 block text-center">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit"
                        class="w-full bg-primary text-white font-medium py-3 rounded-lg hover:bg-primary/90 transition">
                        Mulai Sesi Kasir
                    </button>
                </form>
            @endif

        </div>
    </div>
</div>