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
            'email' => 'required|email|unique:users,email,' . $this->userId,
            'role' => 'required|in:owner,staff',
            'outlet_id' => 'nullable',
        ];

        if (!$this->userId || !empty($this->password)) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules, [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username ini sudah dipakai, silakan pilih yang lain.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.'
        ]);

        $data = [
            'name' => trim($this->name),
            'username' => trim($this->username),
            'email' => trim($this->email),
            'role' => $this->role,
            'outlet_id' => $this->outlet_id ?: null,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            User::where('id', $this->userId)->update($data);

            $this->dispatch('swal', [
                'title' => 'Berhasil!',
                'text' => 'Data karyawan berhasil diperbarui.',
                'icon' => 'success'
            ]);

        } else {
            User::create($data);

            $this->dispatch('swal', [
                'title' => 'Sukses!',
                'text' => 'Karyawan baru berhasil ditambahkan.',
                'icon' => 'success'
            ]);
        }

        $this->closeForm();
        $this->loadData();
    }

    public function delete($id)
    {
        if ($id == auth()->id()) {
            $this->dispatch('swal', [
                'title' => 'Akses Ditolak!',
                'text' => 'Anda tidak bisa menghapus akun Anda sendiri.',
                'icon' => 'error'
            ]);
            return;
        }

        User::findOrFail($id)->delete();
        $this->dispatch('swal', [
            'title' => 'Dihapus!',
            'text' => 'Akun karyawan berhasil dihapus dari sistem.',
            'icon' => 'success'
        ]);
        $this->loadData();
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 max-w-8xl mx-auto bg-gray-50/30 min-h-screen">

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Kelola Karyawan</h1>
            <p class="text-sm text-gray-500 mt-1">Atur data staf, peran kasir, dan penempatan outlet.</p>
        </div>
        @if(!$isFormOpen)
            <button wire:click="openForm"
                class="inline-flex items-center justify-center px-5 py-2.5 bg-primary hover:bg-primary/90 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Karyawan
            </button>
        @endif
    </div>

    @if($isFormOpen)
        <!-- Form Tambah/Edit Karyawan -->
        <div class="bg-white p-6 md:p-8 rounded-2xl border border-gray-200 shadow-sm mb-8" x-data
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex justify-between items-center mb-8 pb-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    {{ $userId ? 'Edit Data Karyawan' : 'Tambah Karyawan Baru' }}
                </h3>
                <button wire:click="closeForm"
                    class="text-gray-400 hover:text-gray-900 transition bg-gray-50 hover:bg-gray-100 p-2 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Lengkap</label>
                    <input type="text" wire:model="name" placeholder="Misal: John Doe"
                        class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50/50 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-primary outline-none transition">
                    @error('name') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Username</label>
                    <input type="text" wire:model="username" placeholder="Misal: john_kasir"
                        class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50/50 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-primary outline-none transition">
                    @error('username') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">Alamat Email</label>
                    <input type="email" wire:model="email" placeholder="john@example.com"
                        class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50/50 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-primary outline-none transition">
                    @error('email') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">
                        Kata Sandi
                        @if($userId)<span class="text-gray-400 text-xs font-normal ml-1">(Kosongkan jika tidak
                        diubah)</span>@endif
                    </label>
                    <input type="password" wire:model="password" placeholder="••••••••"
                        class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50/50 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-primary outline-none transition">
                    @error('password') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 md:col-span-2">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Role/Peran</label>
                        <select wire:model="role"
                            class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50/50 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-primary outline-none transition">
                            <option value="staff">Staff (Kasir)</option>
                            <option value="owner">Owner</option>
                        </select>
                        @error('role') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5">Penempatan Outlet</label>
                        <select wire:model="outlet_id"
                            class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50/50 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-primary outline-none transition">
                            <option value="">-- Bebas Akses --</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="md:col-span-2 flex justify-end gap-3 pt-6 mt-2 border-t border-gray-100">
                    <button type="button" wire:click="closeForm"
                        class="px-6 py-2.5 text-sm font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-6 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary/90 rounded-lg shadow-sm transition">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 font-bold text-gray-600">Info Karyawan</th>
                        <th class="px-6 py-4 font-bold text-gray-600">Peran</th>
                        <th class="px-6 py-4 font-bold text-gray-600">Outlet</th>
                        <th class="px-6 py-4 font-bold text-gray-600 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div
                                        class="w-10 h-10 rounded-full bg-gray-900 text-white flex items-center justify-center font-bold mr-4 flex-shrink-0 shadow-sm">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900">{{ $user->name }}
                                            @if(auth()->id() === $user->id)
                                                <span
                                                    class="text-[9px] bg-gray-100 border border-gray-200 text-gray-600 px-2 py-0.5 rounded-full ml-1.5 font-bold uppercase tracking-wider">Anda</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($user->role === 'owner')
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-gray-900 text-white uppercase tracking-wider">
                                        Owner
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-gray-100 text-gray-600 border border-gray-200 uppercase tracking-wider">
                                        Staff
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <div class="flex items-center gap-2 font-medium">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                    {{ $user->outlet->name ?? 'Semua Akses' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="openForm({{ $user->id }})"
                                        class="p-2 text-gray-500 hover:text-primary hover:bg-gray-100 rounded-lg transition shadow-sm border border-transparent hover:border-gray-200"
                                        title="Edit Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </button>

                                    @if(auth()->id() !== $user->id)
                                        <button type="button" x-data
                                            @click="confirmDeletion(() => $wire.delete({{ $user->id }}), 'Akun {{ $user->name }}')"
                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition shadow-sm border border-transparent hover:border-red-100"
                                            title="Hapus Data">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <p class="font-medium">Belum ada data karyawan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>