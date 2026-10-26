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

        $this->dispatch('notify', [
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

        $this->dispatch('notify', [
            'title' => 'Password Diubah!',
            'text' => 'Kata sandi akun Anda berhasil diperbarui dengan aman.',
            'icon' => 'success'
        ]);
    }

    public function logout()
    {
        $shifts = app(\App\Services\ShiftService::class);
        if ($shift = $shifts->current(auth()->user())) {
            $shifts->close($shift, null, 'Ditutup otomatis saat logout');
        }
        app(\App\Services\ActivityLogger::class)->log('logout', 'Logout');
        session()->forget('current_outlet_id');

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/login');
    }
};
?>

@php $user = auth()->user(); @endphp

<div class="space-y-4">
    <x-ui.page-header title="Profil" subtitle="Kelola informasi pribadi dan keamanan akun." />

    <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
        <!-- Kartu akun -->
        <div class="tile-dark lg:row-span-2 flex flex-col">
            <div class="w-16 h-16 rounded-3xl bg-white text-ink flex items-center justify-center text-xl font-bold">
                {{ collect(explode(' ', $user->name))->take(2)->map(fn($w) => strtoupper(mb_substr($w, 0, 1)))->implode('') }}
            </div>
            <p class="text-xl font-bold mt-5">{{ $user->name }}</p>
            <p class="text-sm text-white/50">{{ '@' . $user->username }}</p>
            <div class="flex flex-wrap gap-1.5 mt-3">
                <span class="badge bg-white/10 text-white capitalize">{{ $user->role }}</span>
                <span class="badge bg-white/10 text-white">{{ \App\Models\Outlet::find(session('current_outlet_id'))->name ?? '-' }}</span>
            </div>
            <div class="mt-auto pt-8">
                <button type="button" x-data @click="confirmLogout(() => $wire.logout())" class="btn w-full bg-white text-ink hover:bg-zinc-100">
                    <x-icon name="logout" class="w-4 h-4" /> Keluar dari akun
                </button>
            </div>
        </div>

        <!-- Informasi akun -->
        <div class="tile lg:col-span-2">
            <h2 class="tile-title mb-4">Informasi Akun</h2>
            <form wire:submit="updateProfile" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="label">Nama lengkap</label>
                    <input type="text" wire:model="name" class="input">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Username</label>
                    <input type="text" wire:model="username" class="input">
                    @error('username') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Email <span class="font-normal text-zinc-400">(opsional)</span></label>
                    <input type="email" wire:model="email" class="input">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn-primary">
                        <span wire:loading.remove wire:target="updateProfile">Simpan Perubahan</span>
                        <span wire:loading wire:target="updateProfile">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Keamanan -->
        <div class="tile lg:col-span-2">
            <h2 class="tile-title mb-4">Ganti Password</h2>
            <form wire:submit="updatePassword" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="label">Password saat ini</label>
                    <input type="password" wire:model="current_password" placeholder="••••••••" class="input">
                    @error('current_password') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Password baru</label>
                    <input type="password" wire:model="new_password" placeholder="••••••••" class="input">
                    @error('new_password') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Konfirmasi password baru</label>
                    <input type="password" wire:model="new_password_confirmation" placeholder="••••••••" class="input">
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn-primary">
                        <span wire:loading.remove wire:target="updatePassword">Perbarui Password</span>
                        <span wire:loading wire:target="updatePassword">Memproses...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
