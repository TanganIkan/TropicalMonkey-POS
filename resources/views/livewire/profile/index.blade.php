<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule as ValidationRule;

new #[Layout('components.layouts.app')] class extends Component {
    #[Rule('required|min:3')]
    public $name = '';

    #[Rule('required|min:3')]
    public $username = '';

    public $email = '';

    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    public function mount()
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
    }

    public function updateProfile()
    {
        $user = auth()->user();

        $this->validate([
            'name' => 'required|min:3',
            'username' => ['required', 'min:3', ValidationRule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', ValidationRule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email ?: null,
        ]);

        $this->dispatch('swal', [
            'title' => 'Profil Diperbarui!',
            'text' => 'Data informasi akun Anda berhasil disimpan.',
            'icon' => 'success'
        ]);
    }

    public function updatePassword()
    {
        $user = auth()->user();

        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ], [
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'new_password.min' => 'Password baru minimal 6 karakter.'
        ]);

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini salah.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        $this->dispatch('swal', [
            'title' => 'Password Diubah!',
            'text' => 'Kata sandi akun Anda berhasil diperbarui dengan aman.',
            'icon' => 'success'
        ]);
    }

    public function logout()
    {
        session()->forget('current_outlet_id');

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login');
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen">
    <!-- Header -->
    <div class="mb-4">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
            Profil Saya
        </h1>
        <p class="text-sm text-gray-500 mt-2">Kelola informasi pribadi dan keamanan akun kamu.</p>
    </div>

    <!-- Info Akun -->
    <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2">
                </path>
            </svg>
            Informasi Akun
        </h2>
        <form wire:submit="updateProfile" class="space-y-5">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Lengkap</label>
                <input type="text" wire:model="name"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 text-sm">
                @error('name') <span class="text-red-500 text-xs mt-1.5 font-medium block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Username</label>
                <input type="text" wire:model="username"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 text-sm">
                @error('username') <span class="text-red-500 text-xs mt-1.5 font-medium block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Email <span
                        class="text-xs font-normal text-gray-400">(Opsional)</span></label>
                <input type="email" wire:model="email"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 text-sm">
                @error('email') <span class="text-red-500 text-xs mt-1.5 font-medium block">{{ $message }}</span>
                @enderror
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="px-6 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-bold hover:bg-black transition-all duration-300 shadow-sm flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="updateProfile">Simpan Perubahan</span>
                    <span wire:loading wire:target="updateProfile">Memproses...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Ganti Password -->
    <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                </path>
            </svg>
            Ganti Password
        </h2>
        <form wire:submit="updatePassword" class="space-y-5">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">Password Saat Ini</label>
                <input type="password" wire:model="current_password" placeholder="••••••••"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 text-sm">
                @error('current_password') <span
                class="text-red-500 text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Password Baru</label>
                    <input type="password" wire:model="new_password" placeholder="••••••••"
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 text-sm">
                    @error('new_password') <span
                    class="text-red-500 text-xs mt-1.5 font-medium block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Konfirmasi Password Baru</label>
                    <input type="password" wire:model="new_password_confirmation" placeholder="••••••••"
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 text-sm">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="px-6 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-bold hover:bg-black transition-all duration-300 shadow-sm flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="updatePassword">Perbarui Password</span>
                    <span wire:loading wire:target="updatePassword">Memproses...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Logout dengan SweetAlert dari app.js -->
    <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
        <button type="button" x-data @click="confirmLogout(() => $wire.logout())"
            class="w-full py-3 text-red-600 bg-white border border-red-200 rounded-xl font-bold hover:bg-red-50 transition-all duration-300 flex items-center justify-center gap-2 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
            Logout / Keluar
        </button>
    </div>
</div>