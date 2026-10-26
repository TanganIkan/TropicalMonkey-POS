<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Outlet;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app')] class extends Component {
    public $isFormOpen = false;
    public $outletId = null;
    public $name = '';
    public $address = '';
    public $phone = '';

    public function openForm($id = null)
    {
        $this->resetValidation();
        $this->reset(['outletId', 'name', 'address', 'phone']);

        if ($id) {
            $outlet = Outlet::findOrFail($id);
            $this->outletId = $outlet->id;
            $this->name = $outlet->name;
            $this->address = $outlet->address;
            $this->phone = $outlet->phone;
        }

        $this->isFormOpen = true;
    }

    public function closeForm()
    {
        $this->isFormOpen = false;
    }

    public function save(ActivityLogger $logger)
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('outlets', 'name')->ignore($this->outletId)],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
        ], [
            'name.required' => 'Nama outlet wajib diisi.',
            'name.unique' => 'Nama outlet sudah dipakai.',
        ]);

        $data = ['name' => trim($this->name), 'address' => trim((string) $this->address) ?: null, 'phone' => trim((string) $this->phone) ?: null];

        if ($this->outletId) {
            $outlet = Outlet::findOrFail($this->outletId);
            $outlet->update($data);
            $logger->log('outlet.update', "Mengubah data outlet {$outlet->name}", $outlet);
        } else {
            $outlet = Outlet::create($data + ['is_active' => true]);
            $logger->log('outlet.update', "Menambah outlet {$outlet->name}", $outlet);
        }

        $this->closeForm();
        $this->dispatch('notify', ['title' => 'Tersimpan!', 'text' => 'Data outlet berhasil disimpan.', 'icon' => 'success']);
    }

    // Outlet tidak dihapus agar riwayat transaksi & stoknya tetap utuh
    public function toggleActive(ActivityLogger $logger, $id)
    {
        $outlet = Outlet::findOrFail($id);

        if ($outlet->is_active && Outlet::where('is_active', true)->count() <= 1) {
            $this->dispatch('notify', ['title' => 'Tidak Bisa', 'text' => 'Minimal harus ada 1 outlet aktif.', 'icon' => 'error']);
            return;
        }

        if ($outlet->is_active && $outlet->id == session('current_outlet_id')) {
            $this->dispatch('notify', ['title' => 'Tidak Bisa', 'text' => 'Outlet yang sedang Anda pakai tidak bisa dinonaktifkan.', 'icon' => 'error']);
            return;
        }

        $outlet->update(['is_active' => !$outlet->is_active]);
        $logger->log('outlet.update', ($outlet->is_active ? 'Mengaktifkan' : 'Menonaktifkan') . " outlet {$outlet->name}", $outlet);
    }

    public function with(): array
    {
        return [
            'outlets' => Outlet::withCount(['transactions' => fn($q) => $q->completed()])->orderByDesc('is_active')->orderBy('name')->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Outlet" subtitle="Kelola cabang toko. Alamat & telepon tampil di struk.">
        <button type="button" wire:click="openForm" class="btn-primary"><x-icon name="plus" class="w-4 h-4" /> Tambah Outlet</button>
    </x-ui.page-header>

    <div class="bento grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($outlets as $outlet)
            <div class="{{ $outlet->id == session('current_outlet_id') ? 'tile-dark' : 'tile' }} flex flex-col {{ $outlet->is_active ? '' : 'opacity-60' }}" wire:key="outlet-{{ $outlet->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-11 h-11 rounded-2xl flex items-center justify-center {{ $outlet->id == session('current_outlet_id') ? 'bg-white/10' : 'bg-zinc-100' }}"><x-icon name="store" class="w-5 h-5" /></div>
                    <span class="{{ $outlet->is_active ? 'badge-success' : 'badge-light' }}">{{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
                <h3 class="text-lg font-semibold mt-4 truncate">{{ $outlet->name }}</h3>
                <p class="text-xs mt-1 {{ $outlet->id == session('current_outlet_id') ? 'text-white/60' : 'text-zinc-500' }}">{{ $outlet->address ?? 'Alamat belum diisi' }}</p>
                <p class="text-xs {{ $outlet->id == session('current_outlet_id') ? 'text-white/60' : 'text-zinc-500' }}">{{ $outlet->phone ?? '-' }}</p>
                <div class="flex items-center justify-between gap-2 mt-auto pt-5">
                    <p class="text-xs {{ $outlet->id == session('current_outlet_id') ? 'text-white/50' : 'text-zinc-400' }}">{{ number_format($outlet->transactions_count, 0, ',', '.') }} transaksi</p>
                    <div class="flex gap-2">
                        <button type="button" wire:click="openForm({{ $outlet->id }})" class="btn-sm {{ $outlet->id == session('current_outlet_id') ? 'btn bg-white text-ink hover:bg-zinc-100' : 'btn-secondary' }}">Edit</button>
                        <button type="button" wire:click="toggleActive({{ $outlet->id }})" class="btn-sm {{ $outlet->is_active ? 'btn-danger' : 'btn-secondary' }}">
                            {{ $outlet->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($isFormOpen)
        <x-ui.modal :title="$outletId ? 'Edit Outlet' : 'Tambah Outlet'" close="closeForm">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="label">Nama outlet</label>
                    <input type="text" wire:model="name" class="input">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Alamat</label>
                    <textarea wire:model="address" rows="2" class="input"></textarea>
                </div>
                <div>
                    <label class="label">Telepon</label>
                    <input type="text" wire:model="phone" class="input">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="closeForm" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
