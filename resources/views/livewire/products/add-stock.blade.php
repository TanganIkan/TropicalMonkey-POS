<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Exceptions\PosException;
use App\Models\Product;
use App\Models\Outlet;
use App\Services\ProductService;
use App\Services\StockService;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;
    public $outlet;

    public $addQuantities = [];

    public $isAddingVariant = false;
    public $newSize = '';
    public $newColor = '';
    public $newSku = '';
    public $newBarcode = '';
    public $notaNumber = '';

    // Edit varian
    public $editingVariantId = null;
    public $editVariant = [];

    public function mount(Product $product)
    {
        // Outlet aktif sudah dijamin ada oleh middleware pos.session
        $this->product = $product;
        $this->outlet = Outlet::find(session('current_outlet_id'));

        $this->initQuantities();
    }

    public function initQuantities()
    {
        if ($this->product->has_variants) {
            foreach ($this->product->activeVariants as $variant) {
                if (!isset($this->addQuantities[$variant->id])) {
                    $this->addQuantities[$variant->id] = 0;
                }
            }
        } else {
            $this->addQuantities['single'] = 0;
        }
    }

    // Hanya varian aktif milik produk ini yang boleh di-restock
    protected function allowedQuantityKeys(): array
    {
        return $this->product->has_variants
            ? $this->product->activeVariants()->pluck('id')->all()
            : ['single'];
    }

    public function getCurrentStock($variantId = null)
    {
        return app(StockService::class)->available($this->product->id, $variantId, $this->outlet->id);
    }

    public function toggleVariantForm()
    {
        $this->isAddingVariant = !$this->isAddingVariant;
        $this->resetValidation();
        if (!$this->isAddingVariant) {
            $this->reset(['newSize', 'newColor', 'newSku', 'newBarcode']);
        }
    }

    public function saveNewVariant(ProductService $products)
    {
        $this->validate(
            [
                'newSize' => 'required|string|max:50',
                'newColor' => 'required|string|max:50',
                'newSku' => 'required|string',
                'newBarcode' => 'nullable|string',
            ],
            [
                'newSize.required' => 'Ukuran wajib diisi.',
                'newColor.required' => 'Warna wajib diisi.',
                'newSku.required' => 'SKU wajib diisi.',
            ],
        );

        // SKU/barcode harus unik di semua produk & varian
        foreach (['newSku' => 'SKU', 'newBarcode' => 'Barcode'] as $field => $label) {
            if ($products->codeTaken($this->$field)) {
                $this->addError($field, "{$label} ini sudah dipakai produk/varian lain.");
                return;
            }
        }

        if ($this->product->variants()->where('size', trim($this->newSize))->where('color', trim($this->newColor))->exists()) {
            $this->addError('newSize', 'Varian dengan ukuran & warna ini sudah ada (cek juga daftar varian nonaktif).');
            return;
        }

        $products->addVariant($this->product, $this->newSize, $this->newColor, $this->newSku, $this->newBarcode);
        $this->initQuantities();

        $this->isAddingVariant = false;
        $this->reset(['newSize', 'newColor', 'newSku', 'newBarcode']);

        $this->dispatch('notify', [
            'title' => 'Varian Ditambahkan!',
            'text' => 'Varian baru berhasil disimpan. Silakan isi jumlah stoknya.',
            'icon' => 'success',
        ]);
    }

    public function save(StockService $stocks)
    {
        $this->validate(
            [
                'addQuantities.*' => 'numeric|min:0',
            ],
            [
                'addQuantities.*.min' => 'Jumlah stok masuk tidak boleh minus.',
            ],
        );

        $quantities = array_intersect_key($this->addQuantities, array_flip($this->allowedQuantityKeys()));
        $added = $stocks->restock($this->product->id, $this->outlet->id, $quantities, $this->notaNumber);

        if ($added > 0) {
            app(\App\Services\ActivityLogger::class)->log('stock.restock', "Restock {$this->product->name}: " . array_sum(array_map('intval', $quantities)) . ' unit' . ($this->notaNumber ? " (nota {$this->notaNumber})" : ''), $this->product);

            // Reset form nota setelah sukses
            $this->reset('notaNumber');
            session()->flash('notify', ['title' => 'Stok Tersimpan', 'text' => 'Stok barang masuk berhasil ditambahkan.', 'icon' => 'success']);
            $this->redirect('/products', navigate: true);
        } else {
            $this->dispatch('notify', [
                'title' => 'Tidak Ada Perubahan',
                'text' => 'Kamu belum memasukkan angka stok baru apapun.',
                'icon' => 'info',
            ]);
        }
    }

    public function deleteVariant(ProductService $products, $variantId)
    {
        if (!$this->product->variants()->whereKey($variantId)->exists()) {
            return;
        }

        $result = $products->deleteVariant($variantId);

        $this->product->refresh();
        unset($this->addQuantities[$variantId]);

        $this->dispatch('notify', $result === 'deleted'
            ? ['title' => 'Dihapus!', 'text' => 'Varian berhasil dihapus permanen.', 'icon' => 'success']
            : [
                'title' => 'Varian Dinonaktifkan',
                'text' => 'Varian ini sudah punya riwayat stok/penjualan, jadi dinonaktifkan (tidak bisa dijual & di-restock) agar riwayatnya tetap utuh. Bisa diaktifkan kembali kapan saja.',
                'icon' => 'success',
            ]);
    }

    public function openEditVariant($variantId)
    {
        $variant = $this->product->variants()->findOrFail($variantId);

        $this->editingVariantId = $variant->id;
        $this->editVariant = $variant->only(['size', 'color', 'sku', 'barcode', 'sell_price', 'cost_price']);
    }

    public function cancelEditVariant()
    {
        $this->reset(['editingVariantId', 'editVariant']);
    }

    public function saveVariant(ProductService $products)
    {
        $variant = $this->product->variants()->findOrFail($this->editingVariantId);

        try {
            $products->updateVariant($variant, $this->editVariant);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $this->product->refresh();
        $this->cancelEditVariant();
        $this->dispatch('notify', ['title' => 'Varian Disimpan', 'text' => 'Data varian berhasil diperbarui.', 'icon' => 'success']);
    }

    public function activateVariant(ProductService $products, $variantId)
    {
        if (!$this->product->variants()->whereKey($variantId)->exists()) {
            return;
        }

        $products->activateVariant($variantId);
        $this->product->refresh();
        $this->initQuantities();

        $this->dispatch('notify', ['title' => 'Diaktifkan!', 'text' => 'Varian bisa dijual dan di-restock kembali.', 'icon' => 'success']);
    }
};
?>

@php $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp

<div class="space-y-4">
    <x-ui.page-header title="Kelola Stok & Varian" back="/products" back-label="Data produk">
        <a href="/products" wire:navigate class="btn-secondary">Batal</a>
        <button type="submit" form="stockForm" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Simpan Stok Masuk</span>
            <span wire:loading wire:target="save">Memproses...</span>
        </button>
    </x-ui.page-header>

    <div class="bento grid-cols-1 md:grid-cols-3">
        <!-- Info produk -->
        <div class="tile md:col-span-2 flex items-center gap-4">
            <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl bg-zinc-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                @if ($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" class="w-full h-full object-cover">
                @else
                    <x-icon name="image" class="w-6 h-6 text-zinc-300" />
                @endif
            </div>
            <div class="min-w-0">
                <h2 class="text-xl md:text-2xl font-bold tracking-tight truncate">{{ $product->name }}</h2>
                <div class="flex flex-wrap gap-1.5 mt-1.5">
                    <span class="badge-light">{{ $product->has_variants ? 'Produk Bervarian' : 'SKU: ' . ($product->sku ?? 'N/A') }}</span>
                    <span class="badge-light">{{ $rp($product->sell_price) }}</span>
                </div>
            </div>
        </div>
        <div class="tile-dark flex flex-col justify-center">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Outlet</p>
            <p class="text-lg font-semibold mt-1 truncate">{{ $outlet->name }}</p>
            <p class="text-xs text-white/50 mt-1">Stok masuk dicatat untuk outlet ini.</p>
        </div>
    </div>

    @if ($product->has_variants)
        <div class="tile" x-data="{ addingVariant: @entangle('isAddingVariant') }">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="tile-title">Daftar Varian</h3>
                <button type="button" @click="addingVariant = !addingVariant" class="btn-secondary btn-sm">
                    <x-icon name="plus" class="w-4 h-4 transition" ::class="addingVariant && 'rotate-45'" />
                    <span x-text="addingVariant ? 'Batal Tambah' : 'Tambah Varian Baru'"></span>
                </button>
            </div>

            <!-- Form tambah varian -->
            <div x-show="addingVariant" x-cloak x-transition class="tile-muted mb-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="label">Ukuran</label>
                        <input type="text" wire:model="newSize" placeholder="XL, 42" class="input !bg-white">
                        @error('newSize') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Warna</label>
                        <input type="text" wire:model="newColor" placeholder="Merah, Hitam" class="input !bg-white">
                        @error('newColor') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">SKU baru</label>
                        <input type="text" wire:model="newSku" placeholder="Kode unik" class="input !bg-white font-mono">
                        @error('newSku') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Barcode</label>
                        <input type="text" wire:model="newBarcode" wire:keydown.enter.prevent="saveNewVariant" placeholder="Scan di sini" class="input !bg-white font-mono">
                        @error('newBarcode') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 mt-4">
                    <p class="text-xs text-zinc-500">Tekan Enter setelah scan barcode untuk menyimpan.</p>
                    <button type="button" wire:click="saveNewVariant" class="btn-primary btn-sm">
                        <span wire:loading.remove wire:target="saveNewVariant">Simpan Varian</span>
                        <span wire:loading wire:target="saveNewVariant">Menyimpan...</span>
                    </button>
                </div>
            </div>

            <form id="stockForm" wire:submit="save">
                <div class="rounded-2xl border border-zinc-200 overflow-x-auto">
                    <table class="table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>Varian</th>
                                <th>SKU / Barcode</th>
                                <th class="text-right">Harga Jual</th>
                                <th class="text-center">Stok</th>
                                <th class="text-center w-36">Jumlah Masuk</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product->variants->where('is_active', true) as $variant)
                                <tr wire:key="v-{{ $variant->id }}">
                                    <td class="font-semibold whitespace-nowrap">{{ $variant->size }} · {{ $variant->color }}</td>
                                    <td class="font-mono text-xs text-zinc-500">{{ $variant->sku }}<br>{{ $variant->barcode ?? '-' }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <span class="font-semibold">{{ $rp($variant->effectiveSellPrice()) }}</span>
                                        @if ($variant->sell_price === null)
                                            <span class="block text-[10px] text-zinc-400">ikut produk</span>
                                        @endif
                                    </td>
                                    <td class="text-center"><span class="badge-light">{{ $this->getCurrentStock($variant->id) }}</span></td>
                                    <td>
                                        <input type="number" min="0" wire:model="addQuantities.{{ $variant->id }}" wire:keydown.enter.prevent=""
                                            class="input !py-1.5 text-center font-semibold">
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <button type="button" wire:click="openEditVariant({{ $variant->id }})" class="btn-icon" title="Edit Varian"><x-icon name="pencil" class="w-4 h-4" /></button>
                                            <button type="button" x-data
                                                @click="confirmAction(() => $wire.deleteVariant({{ $variant->id }}), @js('Hapus varian ' . $variant->size . ' - ' . $variant->color . '?'), 'Jika varian sudah punya riwayat stok/penjualan, varian akan dinonaktifkan (bukan dihapus) agar riwayatnya tetap utuh.', 'Ya, Hapus')"
                                                class="btn-icon hover:!text-red-600 hover:!bg-red-50" title="Hapus / Nonaktifkan Varian"><x-icon name="trash" class="w-4 h-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                            @foreach ($product->variants->where('is_active', false) as $variant)
                                <tr wire:key="vi-{{ $variant->id }}" class="text-zinc-400">
                                    <td class="whitespace-nowrap">{{ $variant->size }} · {{ $variant->color }} <span class="badge-light ml-1">Nonaktif</span></td>
                                    <td class="font-mono text-xs">{{ $variant->sku }}<br>{{ $variant->barcode ?? '-' }}</td>
                                    <td class="text-right">-</td>
                                    <td class="text-center">{{ $this->getCurrentStock($variant->id) }}</td>
                                    <td class="text-center text-xs">-</td>
                                    <td class="text-right">
                                        <button type="button" wire:click="activateVariant({{ $variant->id }})" class="btn-secondary btn-sm" title="Aktifkan Kembali">
                                            <x-icon name="refresh" class="w-3.5 h-3.5" /> Aktifkan
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    @else
        <form id="stockForm" wire:submit="save" class="bento grid-cols-1 sm:grid-cols-2 max-w-2xl">
            <div class="tile">
                <p class="stat-label">Stok saat ini</p>
                <p class="stat-value">{{ $this->getCurrentStock() }}</p>
            </div>
            <div class="tile">
                <label class="label">Jumlah stok masuk</label>
                <input type="number" min="0" wire:model="addQuantities.single" wire:keydown.enter.prevent="" placeholder="0" class="input-lg">
            </div>
        </form>
    @endif

    @if ($editingVariantId)
        <x-ui.modal title="Edit Varian" close="cancelEditVariant" size="lg">
            <form wire:submit="saveVariant" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Ukuran</label>
                        <input type="text" wire:model="editVariant.size" class="input">
                    </div>
                    <div>
                        <label class="label">Warna</label>
                        <input type="text" wire:model="editVariant.color" class="input">
                    </div>
                    <div>
                        <label class="label">SKU</label>
                        <input type="text" wire:model="editVariant.sku" class="input font-mono">
                    </div>
                    <div>
                        <label class="label">Barcode</label>
                        <input type="text" wire:model="editVariant.barcode" class="input font-mono">
                    </div>
                    <div>
                        <label class="label">Harga jual</label>
                        <input type="number" min="0" wire:model="editVariant.sell_price" placeholder="{{ (int) $product->sell_price }} (ikut produk)" class="input">
                    </div>
                    <div>
                        <label class="label">HPP</label>
                        <input type="number" min="0" wire:model="editVariant.cost_price" placeholder="{{ (int) $product->cost_price }} (ikut produk)" class="input">
                    </div>
                </div>
                <p class="text-xs text-zinc-400">Kosongkan harga jual / HPP agar mengikuti harga produk utama.</p>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="cancelEditVariant" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
