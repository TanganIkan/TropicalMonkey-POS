<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public $receiptFooter = '';
    public $lowStockThreshold = 5;
    public $sizeOptions = '';
    public $logoPrimary = null;
    public $logoSecondary = null;

    // Metode bayar: [id => [...]]
    public $methods = [];
    public $newMethodName = '';

    public function mount()
    {
        $this->receiptFooter = Setting::get('receipt_footer');
        $this->lowStockThreshold = Setting::lowStockThreshold();
        $this->sizeOptions = implode(', ', Setting::sizeOptions());
        $this->loadMethods();
    }

    protected function loadMethods()
    {
        $this->methods = PaymentMethod::orderBy('sort_order')->get()
            ->mapWithKeys(fn($m) => [$m->id => $m->only(['code', 'name', 'receipt_label', 'tax_rate', 'is_cash', 'is_active', 'sort_order', 'color'])])
            ->all();
    }

    public function saveStore(ActivityLogger $logger)
    {
        $this->validate([
            'lowStockThreshold' => 'required|integer|min:0',
            'receiptFooter' => 'nullable|string|max:500',
            'logoPrimary' => 'nullable|image|max:2048',
            'logoSecondary' => 'nullable|image|max:2048',
        ], [
            'lowStockThreshold.required' => 'Batas stok menipis wajib diisi.',
            'logoPrimary.image' => 'Logo harus berupa gambar.',
            'logoSecondary.image' => 'Logo harus berupa gambar.',
        ]);

        $sizes = collect(explode(',', $this->sizeOptions))
            ->map(fn($s) => strtoupper(trim($s)))
            ->filter()
            ->unique()
            ->values();

        Setting::set('receipt_footer', trim((string) $this->receiptFooter));
        Setting::set('low_stock_threshold', (string) (int) $this->lowStockThreshold);
        Setting::set('size_options', $sizes->toJson());

        foreach (['logoPrimary' => 'logo_primary', 'logoSecondary' => 'logo_secondary'] as $prop => $key) {
            if ($this->$prop) {
                if ($old = Setting::get($key)) {
                    Storage::disk('public')->delete($old);
                }
                Setting::set($key, $this->$prop->store('branding', 'public'));
            }
        }

        $this->reset(['logoPrimary', 'logoSecondary']);
        $this->sizeOptions = $sizes->implode(', ');
        $logger->log('settings.update', 'Mengubah pengaturan toko');
        $this->dispatch('notify', ['title' => 'Tersimpan!', 'text' => 'Pengaturan toko berhasil disimpan.', 'icon' => 'success']);
    }

    public function saveMethods(ActivityLogger $logger)
    {
        $this->validate([
            'methods.*.name' => 'required|string|max:50',
            'methods.*.receipt_label' => 'required|string|max:50',
            'methods.*.tax_rate' => 'required|numeric|min:0|max:100',
            'methods.*.sort_order' => 'nullable|integer|min:0',
            'methods.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'methods.*.name.required' => 'Nama metode wajib diisi.',
            'methods.*.receipt_label.required' => 'Label struk wajib diisi.',
            'methods.*.tax_rate.*' => 'Pajak harus angka 0–100.',
            'methods.*.color.*' => 'Warna harus format #RRGGBB.',
        ]);

        if (!collect($this->methods)->contains(fn($m) => $m['is_active'])) {
            $this->addError('methods', 'Minimal harus ada 1 metode pembayaran aktif.');
            return;
        }

        $changes = [];
        foreach ($this->methods as $id => $data) {
            $method = PaymentMethod::find($id);
            if (!$method) {
                continue;
            }

            $method->fill([
                'name' => trim($data['name']),
                'receipt_label' => strtoupper(trim($data['receipt_label'])),
                'tax_rate' => (float) $data['tax_rate'],
                'is_cash' => (bool) $data['is_cash'],
                'is_active' => (bool) $data['is_active'],
                'sort_order' => (int) $data['sort_order'],
                'color' => $data['color'],
            ]);

            if ($method->isDirty('tax_rate')) {
                $changes[] = "pajak {$method->name} " . (float) $method->getOriginal('tax_rate') . "% → {$method->tax_rate}%";
            }
            $method->save();
        }

        $this->loadMethods();
        $logger->log('settings.update', 'Mengubah metode pembayaran' . ($changes ? ': ' . implode(', ', $changes) : ''));
        $this->dispatch('notify', ['title' => 'Tersimpan!', 'text' => 'Metode pembayaran berhasil diperbarui. Transaksi lama tetap memakai tarif pajak saat transaksi terjadi.', 'icon' => 'success']);
    }

    public function addMethod(ActivityLogger $logger)
    {
        $name = trim($this->newMethodName);
        $this->validate(['newMethodName' => 'required|string|max:50'], ['newMethodName.required' => 'Isi nama metode baru.']);

        $base = Str::slug($name, '_');
        $code = $base;
        for ($i = 2; PaymentMethod::where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        PaymentMethod::create([
            'code' => $code,
            'name' => $name,
            'receipt_label' => strtoupper($name),
            'tax_rate' => 0,
            'is_cash' => false,
            'is_active' => true,
            'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
            'color' => '#6b7280',
        ]);

        $this->reset('newMethodName');
        $this->loadMethods();
        $logger->log('settings.update', "Menambah metode pembayaran {$name}");
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Pengaturan" subtitle="Identitas toko, struk, stok, dan metode pembayaran." />

    <form wire:submit="saveStore" class="bento grid-cols-1 lg:grid-cols-3 items-start">
        <!-- Logo -->
        <div class="tile">
            <h2 class="tile-title mb-4">Logo Sidebar</h2>
            <div class="space-y-4">
                @foreach (['logoPrimary' => ['logo_primary', 'Logo 1', 'images/logo1.jpeg'], 'logoSecondary' => ['logo_secondary', 'Logo 2', 'images/logo2.jpeg']] as $prop => [$key, $label, $default])
                    <div class="flex items-center gap-3">
                        @if ($this->$prop)
                            <img src="{{ $this->$prop->temporaryUrl() }}" class="w-14 h-14 rounded-full object-cover border border-zinc-200">
                        @else
                            <img src="{{ \App\Models\Setting::get($key) ? asset('storage/' . \App\Models\Setting::get($key)) : asset($default) }}" class="w-14 h-14 rounded-full object-cover border border-zinc-200">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $label }}</p>
                            <label class="btn-secondary btn-sm mt-1 cursor-pointer">
                                <x-icon name="image" class="w-3.5 h-3.5" /> Pilih gambar
                                <input type="file" wire:model="{{ $prop }}" accept="image/*" class="hidden">
                            </label>
                            @error($prop) <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Struk & stok -->
        <div class="tile lg:col-span-2">
            <h2 class="tile-title mb-4">Toko & Struk</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="label">Footer struk</label>
                    <textarea wire:model="receiptFooter" rows="3" placeholder="Satu baris per kalimat" class="input"></textarea>
                    <p class="text-xs text-zinc-400 mt-1">Baris pertama dicetak tebal, baris berikutnya kecil.</p>
                </div>
                <div>
                    <label class="label">Batas stok menipis</label>
                    <input type="number" min="0" wire:model="lowStockThreshold" class="input">
                    <p class="text-xs text-zinc-400 mt-1">Barang dengan stok ≤ angka ini ditandai menipis.</p>
                    @error('lowStockThreshold') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Pilihan ukuran</label>
                    <input type="text" wire:model="sizeOptions" placeholder="S, M, L, XL, XXL" class="input">
                    <p class="text-xs text-zinc-400 mt-1">Pisahkan dengan koma.</p>
                </div>
            </div>
            <div class="flex justify-end mt-5">
                <button type="submit" class="btn-primary">
                    <span wire:loading.remove wire:target="saveStore">Simpan Pengaturan Toko</span>
                    <span wire:loading wire:target="saveStore">Menyimpan...</span>
                </button>
            </div>
        </div>
    </form>

    <!-- Metode pembayaran -->
    <form wire:submit="saveMethods" class="tile">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 mb-4">
            <div>
                <h2 class="tile-title">Metode Pembayaran & Pajak</h2>
                <p class="tile-sub max-w-2xl">Tarif pajak disimpan di setiap transaksi, jadi mengubah tarif tidak mengubah struk & laporan lama. Metode yang tidak dipakai cukup dinonaktifkan.</p>
            </div>
            <div class="flex gap-2">
                <input type="text" wire:model="newMethodName" wire:keydown.enter.prevent="addMethod" placeholder="Metode baru, mis. Transfer Bank" class="input !py-2 md:!w-64">
                <button type="button" wire:click="addMethod" class="btn-secondary !py-2 whitespace-nowrap">+ Tambah</button>
            </div>
        </div>
        @error('newMethodName') <p class="field-error -mt-2 mb-3">{{ $message }}</p> @enderror

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            @foreach ($methods as $id => $m)
                <div class="tile-muted space-y-3 {{ $m['is_active'] ? '' : 'opacity-60' }}" wire:key="pm-{{ $id }}">
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="methods.{{ $id }}.color" class="w-9 h-9 rounded-xl border border-zinc-200 bg-white p-1 cursor-pointer flex-shrink-0">
                        <input type="text" wire:model="methods.{{ $id }}.name" class="input !py-2 !bg-white font-semibold">
                    </div>
                    <p class="text-[10px] text-zinc-400 font-mono -mt-1">kode: {{ $m['code'] }}</p>
                    <div class="grid grid-cols-[1fr_5.5rem] gap-2">
                        <div>
                            <label class="label">Label struk</label>
                            <input type="text" wire:model="methods.{{ $id }}.receipt_label" class="input !py-2 !bg-white">
                        </div>
                        <div>
                            <label class="label">Pajak %</label>
                            <input type="number" step="0.01" min="0" max="100" wire:model="methods.{{ $id }}.tax_rate" class="input !py-2 !bg-white">
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex gap-3">
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium cursor-pointer"><input type="checkbox" wire:model="methods.{{ $id }}.is_cash" class="checkbox"> Tunai</label>
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium cursor-pointer"><input type="checkbox" wire:model="methods.{{ $id }}.is_active" class="checkbox"> Aktif</label>
                        </div>
                        <label class="inline-flex items-center gap-1.5 text-xs text-zinc-500">Urutan
                            <input type="number" min="0" wire:model="methods.{{ $id }}.sort_order" class="input !py-1 !px-2 !w-14 !bg-white !text-xs">
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('methods') <p class="field-error">{{ $message }}</p> @enderror
        @foreach ($errors->get('methods.*') as $messages)
            <p class="field-error">{{ $messages[0] }}</p>
        @endforeach

        <div class="flex justify-end mt-5">
            <button type="submit" class="btn-primary">
                <span wire:loading.remove wire:target="saveMethods">Simpan Metode Pembayaran</span>
                <span wire:loading wire:target="saveMethods">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
