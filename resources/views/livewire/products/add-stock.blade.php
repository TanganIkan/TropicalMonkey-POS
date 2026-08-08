<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Outlet;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    public Product $product;
    public $outlet;

    public $addQuantities = [];

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->outlet = Outlet::find(session('current_outlet_id'));

        if ($this->product->has_variants) {
            foreach ($this->product->variants as $variant) {
                $this->addQuantities[$variant->id] = 0;
            }
        } else {
            $this->addQuantities['single'] = 0;
        }
    }

    public function getCurrentStock($variantId = null)
    {
        $stock = Stock::where('product_id', $this->product->id)
            ->where('outlet_id', $this->outlet->id)
            ->where('product_variant_id', $variantId)
            ->first();

        return $stock ? $stock->quantity : 0;
    }

    public function save()
    {
        $this->validate([
            'addQuantities.*' => 'numeric|min:0'
        ], [
            'addQuantities.*.min' => 'Jumlah stok masuk tidak boleh minus.'
        ]);

        DB::transaction(function () {
            if ($this->product->has_variants) {
                foreach ($this->addQuantities as $variantId => $qtyToAdd) {
                    if ($qtyToAdd > 0) {
                        $stock = Stock::firstOrCreate([
                            'product_id' => $this->product->id,
                            'product_variant_id' => $variantId,
                            'outlet_id' => $this->outlet->id,
                        ], ['quantity' => 0]);

                        $stock->increment('quantity', $qtyToAdd);
                    }
                }
            } else {
                $qtyToAdd = $this->addQuantities['single'] ?? 0;

                if ($qtyToAdd > 0) {
                    $stock = Stock::firstOrCreate([
                        'product_id' => $this->product->id,
                        'product_variant_id' => null,
                        'outlet_id' => $this->outlet->id,
                    ], ['quantity' => 0]);

                    $stock->increment('quantity', $qtyToAdd);
                }
            }
        });

        session()->flash('success', "Stok barang masuk untuk {$this->product->name} berhasil ditambahkan!");
        $this->redirect('/products', navigate: true);
    }

    public function deleteVariant($variantId)
    {
        DB::transaction(function () use ($variantId) {
            Stock::where('product_variant_id', $variantId)->delete();

            ProductVariant::where('id', $variantId)->delete();
        });

        $this->product->refresh();

        unset($this->addQuantities[$variantId]);
        session()->flash('success', 'Varian yang salah berhasil dihapus permanen!');
    }
};
?>

<div class="p-4 md:p-6 flex flex-col space-y-4 md:space-y-6">
    <form wire:submit="save">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
            <div>
                <a href="/products" wire:navigate
                    class="text-sm text-gray-500 hover:text-gray-700 flex items-center mb-2 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali ke Data Produk
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Barang Masuk (Tambah Stok)</h1>
            </div>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <a href="/products" wire:navigate
                    class="flex-1 sm:flex-none flex items-center justify-center px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">Batal</a>
                <button type="submit"
                    class="flex-1 sm:flex-none flex items-center justify-center px-5 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition shadow-sm shadow-primary/30"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Stok Masuk</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
            <!-- Info Produk -->
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                <div
                    class="w-16 h-16 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($product->image)
                        <img src="{{ asset('storage/' . $product->image) }}" class="w-full h-full object-cover">
                    @else
                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $product->name }}</h2>
                    <p class="text-sm text-gray-500">
                        {{ $product->has_variants ? 'Produk Bervarian' : 'SKU: ' . ($product->sku ?? 'N/A') }}
                    </p>
                </div>
            </div>

            <!-- Form Input Stok -->
            @if($product->has_variants)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-gray-700">Varian (Ukuran - Warna)</th>
                                <th class="px-4 py-3 font-semibold text-gray-700 text-center">SKU Varian</th>
                                <th class="px-4 py-3 font-semibold text-gray-700 text-center">Barcode</th>
                                <th class="px-4 py-3 font-semibold text-gray-700 text-center w-32">Stok Saat Ini</th>
                                <th class="px-4 py-3 font-semibold text-primary text-center w-40">Jumlah Stok Masuk (Baru)
                                <th class="px-4 py-3 font-semibold text-gray-700 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($product->variants as $variant)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $variant->size }} - {{ $variant->color }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-500 font-mono text-xs">{{ $variant->sku }}</td>

                                    <!-- Menampilkan Barcode -->
                                    <td class="px-4 py-3 text-center text-gray-500 font-mono text-xs">
                                        {{ $variant->barcode ?? '-' }}
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center justify-center bg-gray-100 text-gray-700 font-bold px-3 py-1 rounded-md">{{ $this->getCurrentStock($variant->id) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="0" wire:model="addQuantities.{{ $variant->id }}"
                                            class="w-full text-center px-3 py-2 border border-blue-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none bg-blue-50/30 text-blue-900 font-bold">
                                        @error('addQuantities.' . $variant->id) <span
                                        class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" wire:click="deleteVariant({{ $variant->id }})"
                                            wire:confirm="Yakin ingin menghapus varian '{{ $variant->size }} - {{ $variant->color }}' ini secara permanen? Seluruh data stok varian ini akan ikut terhapus!"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                            title="Hapus Varian Salah">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="max-w-md">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 mb-4">
                        <span class="text-gray-700 font-medium">Stok Saat Ini</span>
                        <span class="text-xl font-bold text-gray-900">{{ $this->getCurrentStock() }}</span>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-primary mb-2">Jumlah Stok Masuk (Baru)</label>
                        <input type="number" min="0" wire:model="addQuantities.single"
                            class="w-full px-4 py-3 border-2 border-blue-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none bg-blue-50/30 text-blue-900 font-bold text-lg"
                            placeholder="Contoh: 10">
                        @error('addQuantities.single') <span class="text-sm text-red-500 mt-1 block">{{ $message }}</span>
                        @enderror
                        <p class="text-xs text-gray-500 mt-2">*Angka yang diisi akan ditambahkan ke total stok saat ini.</p>
                    </div>
                </div>
            @endif
        </div>
    </form>
</div>