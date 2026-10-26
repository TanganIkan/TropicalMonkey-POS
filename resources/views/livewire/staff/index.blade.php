<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Outlet;
use Illuminate\Support\Facades\Hash;

new #[Layout('components.layouts.app')] class extends Component {
    public $users = [];
    public $outlets = [];

    public $isFormOpen = false;
    public $userId = null;
    public $name = '';
    public $username = '';
    public $email = '';
    public $password = '';
    public $role = 'staff';
    public $outlet_id = '';

    public function mount()
    {
        if (auth()->user()->role !== 'owner') {
            $this->redirect('/pos', navigate: true);
        }
        $this->loadData();
    }

    public function loadData()
    {
        $this->users = User::with('outlet')->orderBy('name')->get();
        $this->outlets = Outlet::where('is_active', true)->orderBy('name')->get();
    }

    public function openForm($id = null)
    {
        $this->resetValidation();
        $this->resetForm();

        if ($id) {
            $user = User::findOrFail($id);
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->username = $user->username;
            $this->email = $user->email;
            $this->role = $user->role;
            $this->outlet_id = $user->outlet_id;
        }

        $this->isFormOpen = true;
    }

    public function closeForm()
    {
        $this->isFormOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->userId = null;
        $this->name = '';
        $this->username = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'staff';
        $this->outlet_id = '';
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $this->userId,
            // Email opsional, sama seperti di halaman Profil
            'email' => 'nullable|email|unique:users,email,' . $this->userId,
            'role' => 'required|in:owner,staff',
            'outlet_id' => 'nullable',
        ];

        // Owner tidak boleh menurunkan perannya sendiri (bisa terkunci dari halaman owner)
        if ($this->userId && $this->userId == auth()->id() && $this->role !== auth()->user()->role) {
            $this->addError('role', 'Anda tidak bisa mengubah peran akun Anda sendiri.');
            return;
        }

        if (!$this->userId || !empty($this->password)) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules, [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username ini sudah dipakai, silakan pilih yang lain.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.'
        ]);

        $data = [
            'name' => trim($this->name),
            'username' => trim($this->username),
            'email' => trim((string) $this->email) ?: null,
            'role' => $this->role,
            'outlet_id' => $this->outlet_id ?: null,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            User::where('id', $this->userId)->update($data);
            app(\App\Services\ActivityLogger::class)->log('staff.update', "Mengubah data karyawan {$data['name']}");

            $this->dispatch('notify', [
                'title' => 'Berhasil!',
                'text' => 'Data karyawan berhasil diperbarui.',
                'icon' => 'success'
            ]);

        } else {
            User::create($data);
            app(\App\Services\ActivityLogger::class)->log('staff.update', "Menambah karyawan {$data['name']} ({$data['role']})");

            $this->dispatch('notify', [
                'title' => 'Sukses!',
                'text' => 'Karyawan baru berhasil ditambahkan.',
                'icon' => 'success'
            ]);
        }

        $this->closeForm();
        $this->loadData();
    }

    // Karyawan tidak dihapus (riwayat transaksinya harus tetap utuh), cukup dinonaktifkan
    public function toggleActive($id)
    {
        if ($id == auth()->id()) {
            $this->dispatch('notify', [
                'title' => 'Akses Ditolak!',
                'text' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.',
                'icon' => 'error'
            ]);
            return;
        }

        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        app(\App\Services\ActivityLogger::class)->log('staff.update', ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan') . " akun {$user->name}");

        $this->dispatch('notify', [
            'title' => $user->is_active ? 'Diaktifkan!' : 'Dinonaktifkan!',
            'text' => $user->is_active
                ? 'Akun karyawan bisa digunakan untuk login kembali.'
                : 'Akun karyawan tidak bisa login lagi. Riwayat transaksinya tetap tersimpan.',
            'icon' => 'success'
        ]);
        $this->loadData();
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Karyawan" subtitle="Atur data staf, peran, dan penempatan outlet.">
        <button wire:click="openForm" class="btn-primary"><x-icon name="plus" class="w-4 h-4" /> Tambah Karyawan</button>
    </x-ui.page-header>

    <div class="bento grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($users as $user)
            <div class="tile flex flex-col {{ $user->is_active ? '' : 'opacity-60' }}" wire:key="u-{{ $user->id }}">
                <div class="flex items-start gap-3">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-sm font-bold flex-shrink-0 {{ $user->role === 'owner' ? 'bg-ink text-white' : 'bg-zinc-100 text-zinc-700' }}">
                        {{ collect(explode(' ', $user->name))->take(2)->map(fn($w) => strtoupper(mb_substr($w, 0, 1)))->implode('') }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold truncate">{{ $user->name }}</p>
                        <p class="text-xs text-zinc-400 truncate">{{ '@' . $user->username }}{{ $user->email ? ' · ' . $user->email : '' }}</p>
                        <div class="flex flex-wrap gap-1 mt-2">
                            <span class="{{ $user->role === 'owner' ? 'badge-dark' : 'badge-light' }}">{{ $user->role === 'owner' ? 'Owner' : 'Staff' }}</span>
                            @if (auth()->id() === $user->id) <span class="badge-light">Anda</span> @endif
                            @if (!$user->is_active) <span class="badge-danger">Nonaktif</span> @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 mt-4 pt-4 border-t border-zinc-100">
                    <p class="text-xs text-zinc-500 flex items-center gap-1.5 min-w-0"><x-icon name="store" class="w-3.5 h-3.5" /> <span class="truncate">{{ $user->outlet->name ?? 'Semua outlet' }}</span></p>
                    <div class="flex gap-1">
                        <button wire:click="openForm({{ $user->id }})" class="btn-icon" title="Edit Data"><x-icon name="pencil" class="w-4 h-4" /></button>
                        @if (auth()->id() !== $user->id)
                            @if ($user->is_active)
                                <button type="button" x-data
                                    @click="confirmAction(() => $wire.toggleActive({{ $user->id }}), 'Nonaktifkan akun?', @js($user->name . ' tidak akan bisa login lagi. Riwayat transaksinya tetap tersimpan.'), 'Ya, Nonaktifkan')"
                                    class="btn-icon hover:!text-red-600 hover:!bg-red-50" title="Nonaktifkan Akun"><x-icon name="ban" class="w-4 h-4" /></button>
                            @else
                                <button type="button" wire:click="toggleActive({{ $user->id }})" class="btn-icon hover:!text-emerald-600" title="Aktifkan Kembali"><x-icon name="check-circle" class="w-4 h-4" /></button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="tile col-span-full"><x-ui.empty icon="users" title="Belum ada data karyawan" /></div>
        @endforelse
    </div>

    @if ($isFormOpen)
        <x-ui.modal :title="$userId ? 'Edit Karyawan' : 'Tambah Karyawan'" close="closeForm" size="lg">
            <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nama lengkap</label>
                    <input type="text" wire:model="name" placeholder="mis. John Doe" class="input">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Username</label>
                    <input type="text" wire:model="username" placeholder="mis. john_kasir" class="input">
                    @error('username') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Email <span class="font-normal text-zinc-400">(opsional)</span></label>
                    <input type="email" wire:model="email" placeholder="john@example.com" class="input">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Kata sandi @if ($userId)<span class="font-normal text-zinc-400">(kosongkan jika tidak diubah)</span>@endif</label>
                    <input type="password" wire:model="password" placeholder="••••••••" class="input">
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Peran</label>
                    <select wire:model="role" @disabled($userId && $userId == auth()->id())
                        title="{{ $userId && $userId == auth()->id() ? 'Peran akun sendiri tidak bisa diubah' : '' }}" class="select">
                        <option value="staff">Staff (Kasir)</option>
                        <option value="owner">Owner</option>
                    </select>
                    @error('role') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Penempatan outlet</label>
                    <select wire:model="outlet_id" class="select">
                        <option value="">Semua outlet</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 grid grid-cols-2 gap-2 pt-2">
                    <button type="button" wire:click="closeForm" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
