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

    public $successMessage = '';

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

        $this->successMessage = 'Data akun berhasil diperbarui.';
    }

    public function updatePassword()
    {
        $user = auth()->user();

        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ], [
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini salah.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->successMessage = 'Password berhasil diperbarui.';
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

<div class="p-4 md:p-6 max-w-8xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Profil Saya</h1>
        <p class="text-sm text-gray-500 mt-1">Kelola informasi akun kamu.</p>
    </div>

    @if($successMessage)
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm p-3 rounded-lg">
            {{ $successMessage }}
        </div>
    @endif

    <!-- Info Akun -->
    <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
        <h2 class="text-lg font-bold mb-4">Informasi Akun</h2>
        <form wire:submit="updateProfile" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" wire:model="name"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" wire:model="username"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                @error('username') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email (opsional)</label>
                <input type="email" wire:model="email"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                @error('email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition">
                Simpan Perubahan
            </button>
        </form>
    </div>

    <!-- Ganti Password -->
    <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
        <h2 class="text-lg font-bold mb-4">Ganti Password</h2>
        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password Saat Ini</label>
                <input type="password" wire:model="current_password"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                @error('current_password') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                    <input type="password" wire:model="new_password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                    @error('new_password') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" wire:model="new_password_confirmation"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>

            <button type="submit"
                class="px-5 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition">
                Ubah Password
            </button>
        </form>
    </div>

    <!-- Logout -->
    <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
        <button wire:click="logout"
            class="w-full py-3 text-red-600 border border-red-200 rounded-lg font-medium hover:bg-red-50 transition flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
            Logout
        </button>
    </div>
</div>