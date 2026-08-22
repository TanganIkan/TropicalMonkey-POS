<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Outlet;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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

    public function mount(Product $product)
    {
        $outletId = session('current_outlet_id');

        // LAPISAN 1: Jika belum pilih outlet / sesi hilang, PAKSA LOGOUT
        if (!$outletId) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            session()->flash('error', 'Sesi toko terputus. Silakan login dan pilih outlet kembali.');
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->product = $product;
        $this->outlet = Outlet::find($outletId);

        // LAPISAN 2: Jika ID outlet ada tapi dihapus dari database, PAKSA LOGOUT
        if (!$this->outlet) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            session()->flash('error', 'Outlet tidak valid. Silakan login kembali.');
            $this->redirect('/login', navigate: true);
            return;
        }

        $this->initQuantities();
    }

    public function initQuantities()
    {
        if ($this->product->has_variants) {
            foreach ($this->product->variants as $variant) {
                if (!isset($this->addQuantities[$variant->id])) {
                    $this->addQuantities[$variant->id] = 0;
                }
            }
        } else {
            $this->addQuantities['single'] = 0;
        }
    }

    public function getCurrentStock($variantId = null)
    {
        $stock = Stock::where('product_id', $this->product->id)->where('outlet_id', $this->outlet->id)->where('product_variant_id', $variantId)->first();

        return $stock ? $stock->quantity : 0;
    }

    public function toggleVariantForm()
    {
        $this->isAddingVariant = !$this->isAddingVariant;
        $this->resetValidation();
        if (!$this->isAddingVariant) {
            $this->reset(['newSize', 'newColor', 'newSku', 'newBarcode']);
        }
    }

    public function saveNewVariant()
    {
        $this->validate(
            [
                'newSize' => 'required|string|max:50',
                'newColor' => 'required|string|max:50',
                'newSku' => 'required|string|unique:product_variants,sku',
                'newBarcode' => 'nullable|string|unique:product_variants,barcode',
            ],
            [
                'newSize.required' => 'Ukuran wajib diisi.',
                'newColor.required' => 'Warna wajib diisi.',
                'newSku.required' => 'SKU wajib diisi.',
                'newSku.unique' => 'SKU ini sudah dipakai varian lain.',
                'newBarcode.unique' => 'Barcode ini sudah dipakai varian lain.',
            ],
        );

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'size' => trim($this->newSize),
            'color' => trim($this->newColor),
            'sku' => trim($this->newSku),
            'barcode' => trim($this->newBarcode) ?: null,
        ]);

        $this->product->refresh();
        $this->initQuantities();

        $this->isAddingVariant = false;
        $this->reset(['newSize', 'newColor', 'newSku', 'newBarcode']);

        $this->dispatch('swal', [
            'title' => 'Varian Ditambahkan!',
            'text' => 'Varian baru berhasil disimpan. Silakan isi jumlah stoknya.',
            'icon' => 'success',
        ]);
    }

    public function save()
    {
        $this->validate(
            [
                'addQuantities.*' => 'numeric|min:0',
            ],
            [
                'addQuantities.*.min' => 'Jumlah stok masuk tidak boleh minus.',
            ],
        );

        $hasStockAdded = false;

        DB::transaction(function () use (&$hasStockAdded) {
            if ($this->product->has_variants) {
                foreach ($this->addQuantities as $variantId => $qtyToAdd) {
                    if ($qtyToAdd > 0) {

                        // 1. CATAT KE RIWAYAT (Stock History)
                        \App\Models\StockHistory::create([
                            'product_id' => $this->product->id,
                            'product_variant_id' => $variantId,
                            'outlet_id' => $this->outlet->id,
                            'quantity' => $qtyToAdd,
                            'type' => 'in',
                            'nota_number' => trim($this->notaNumber) ?: null, // Simpan nota
                        ]);

                        // 2. UPDATE MASTER STOK
                        $stock = Stock::firstOrCreate(
                            [
                                'product_id' => $this->product->id,
                                'product_variant_id' => $variantId,
                                'outlet_id' => $this->outlet->id,
                            ],
                            ['quantity' => 0],
                        );

                        $stock->increment('quantity', $qtyToAdd);
                        $hasStockAdded = true;
                    }
                }
            } else {
                $qtyToAdd = $this->addQuantities['single'] ?? 0;

                if ($qtyToAdd > 0) {

                    // 1. CATAT KE BUKU RIWAYAT (Stock History)
                    \App\Models\StockHistory::create([
                        'product_id' => $this->product->id,
                        'product_variant_id' => null,
                        'outlet_id' => $this->outlet->id,
                        'quantity' => $qtyToAdd,
                        'type' => 'in',
                        'nota_number' => trim($this->notaNumber) ?: null, // Simpan nota
                    ]);

                    // 2. UPDATE MASTER STOK
                    $stock = Stock::firstOrCreate(
                        [
                            'product_id' => $this->product->id,
                            'product_variant_id' => null,
                            'outlet_id' => $this->outlet->id,
                        ],
                        ['quantity' => 0],
                    );

                    $stock->increment('quantity', $qtyToAdd);
                    $hasStockAdded = true;
                }
            }
        });

        if ($hasStockAdded) {
            // Reset form nota setelah sukses
            $this->reset('notaNumber');
            $this->dispatch('stock-saved');
        } else {
            $this->dispatch('swal', [
                'title' => 'Tidak Ada Perubahan',
                'text' => 'Kamu belum memasukkan angka stok baru apapun.',
                'icon' => 'info',
            ]);
        }
    }

    public function deleteVariant($variantId)
    {
        try {
            DB::transaction(function () use ($variantId) {
                \App\Models\Stock::where('product_variant_id', $variantId)->delete();
                \App\Models\StockHistory::where('product_variant_id', $variantId)->delete();
                \App\Models\ProductVariant::where('id', $variantId)->delete();
            });

            $this->product->refresh();
            unset($this->addQuantities[$variantId]);

            $this->dispatch('swal', [
                'title' => 'Dihapus!',
                'text' => 'Varian berhasil dihapus permanen.',
                'icon' => 'success',
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                $this->dispatch('swal', [
                    'title' => 'Tidak Bisa Dihapus!',
                    'text' => 'Varian ini sudah memiliki riwayat transaksi/penjualan. Demi keamanan laporan keuangan, varian ini tidak bisa dihapus.',
                    'icon' => 'error'
                ]);
            } else {
                $this->dispatch('swal', [
                    'title' => 'Terjadi Kesalahan!',
                    'text' => 'Gagal menghapus varian: ' . $e->getMessage(),
                    'icon' => 'error'
                ]);
            }
        }
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen" x-data
    @stock-saved.window="
        Swal.fire({
            title: 'Stok Tersimpan!',
            text: 'Jumlah stok barang masuk berhasil ditambahkan ke sistem.',
            icon: 'success',
            confirmButtonColor: '#111827',
            heightAuto: false,
            scrollbarPadding: false
        }).then(() => {
            Livewire.navigate('/products');
        })">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2 gap-4">
        <div>
            <a href="/products" wire:navigate
                class="text-sm text-gray-500 hover:text-gray-900 flex items-center mb-2 transition-all duration-300 ease-in-out font-medium">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Kembali ke Data Produk
            </a>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Barang Masuk (Tambah Stok)</h1>
        </div>
        <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
            <a href="/products" wire:navigate
                class="flex-1 sm:flex-none flex items-center justify-center px-6 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-50 hover:border-gray-300 transition-all duration-300 ease-in-out shadow-sm">Batal</a>
            <button type="submit" form="stockForm"
                class="flex-1 sm:flex-none flex items-center justify-center px-6 py-2.5 bg-gray-900 text-white rounded-lg font-bold hover:bg-black transition-all duration-300 ease-in-out shadow-lg shadow-gray-900/20"
                wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Simpan Stok Masuk</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </button>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-8 shadow-sm">
        <!-- Info Produk -->
        <div class="flex items-center gap-5 mb-6 pb-6 border-b border-gray-100">
            <div
                class="w-16 h-16 md:w-20 md:h-20 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                @if ($product->image)
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
                <h2 class="text-xl md:text-2xl font-bold text-gray-900">{{ $product->name }}</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">
                    {{ $product->has_variants ? 'Produk Bervarian' : 'SKU: ' . ($product->sku ?? 'N/A') }}
                </p>
            </div>
        </div>

        {{-- <div class="mb-6 p-5 bg-blue-50/50 border border-blue-100 rounded-xl">
            <label class="block text-sm font-bold text-gray-900 mb-1.5 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Nomor Nota / Surat Jalan (Opsional)
            </label>
            <input type="text" wire:model="notaNumber" placeholder="Contoh: INV-2026-001"
                class="w-full md:w-1/2 text-sm px-4 py-2.5 border border-gray-200 bg-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none shadow-sm transition-all">
            <p class="text-xs text-gray-500 mt-2 font-medium">Nomor nota ini akan dilampirkan ke semua varian yang diisi
                stoknya di bawah.</p>
        </div> --}}

        @if ($product->has_variants)
            <div x-data="{ addingVariant: @entangle('isAddingVariant') }">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="font-bold text-gray-900 text-lg">Daftar Varian</h3>
                    <button type="button" @click="addingVariant = !addingVariant"
                        class="text-sm font-bold flex items-center gap-1.5 transition-all duration-300 ease-in-out px-3 py-1.5 rounded-lg border"
                        :class="addingVariant ? 'text-gray-500 bg-gray-50 border-gray-200 hover:text-gray-900 hover:bg-gray-100' : 'text-gray-900 bg-white border-gray-200 shadow-sm hover:border-gray-900'">
                        <svg class="w-4 h-4 transform transition-transform duration-300 ease-in-out"
                            :class="addingVariant ? 'rotate-45' : 'rotate-0'" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span x-text="addingVariant ? 'Batal Tambah' : 'Tambah Varian Baru'"></span>
                    </button>
                </div>

                <!-- Form Tambah Varian -->
                <div x-show="addingVariant" x-transition:enter="transition-all ease-in-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-[-10px] scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition-all ease-in-out duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-[-10px] scale-95"
                    class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6 shadow-inner" style="display: none;">

                    <div class="flex items-center gap-2 mb-5 text-gray-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                            </path>
                        </svg>
                        <h4 class="font-bold text-sm">Informasi Varian Baru</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Ukuran</label>
                            <input type="text" wire:model="newSize" placeholder="Misal: XL, 42"
                                class="w-full text-sm px-4 py-2.5 border border-gray-200 bg-white rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none shadow-sm transition-all duration-300 ease-in-out">
                            @error('newSize') <span
                            class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Warna</label>
                            <input type="text" wire:model="newColor" placeholder="Misal: Merah, Hitam"
                                class="w-full text-sm px-4 py-2.5 border border-gray-200 bg-white rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none shadow-sm transition-all duration-300 ease-in-out">
                            @error('newColor') <span
                            class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">SKU Baru</label>
                            <input type="text" wire:model="newSku" placeholder="Kode Unik SKU"
                                class="w-full text-sm px-4 py-2.5 border border-gray-200 bg-white rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none shadow-sm transition-all duration-300 ease-in-out">
                            @error('newSku') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-900 mb-1.5 flex items-center gap-1">Barcode
                                (Wajib Scan)</label>
                            <input type="text" wire:model="newBarcode" wire:keydown.enter.prevent="saveNewVariant"
                                placeholder="Arahkan Kursor & Scan"
                                class="w-full text-sm px-4 py-2.5 border-2 border-gray-300 bg-white rounded-lg focus:border-gray-900 focus:ring-0 outline-none shadow-sm transition-all duration-300 ease-in-out">
                            @error('newBarcode') <span
                            class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-gray-500 mt-1.5 font-medium">*Tekan Enter atau Scan Barcode untuk
                                menyimpan otomatis.</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="button" wire:click="saveNewVariant"
                            class="px-6 py-2.5 bg-gray-900 text-white text-sm font-bold rounded-lg hover:bg-black transition-all duration-300 ease-in-out shadow-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                            Simpan Varian
                        </button>
                    </div>
                </div>
            </div>

            <form id="stockForm" wire:submit="save">
                <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-4 font-bold text-gray-700">Varian (Ukuran - Warna)</th>
                                <th class="px-5 py-4 font-bold text-gray-700 text-center">SKU</th>
                                <th class="px-5 py-4 font-bold text-gray-700 text-center">Barcode</th>
                                <th class="px-5 py-4 font-bold text-gray-700 text-center w-32">Stok Saat Ini</th>
                                <th class="px-5 py-4 font-bold text-gray-900 text-center w-40">Jumlah Masuk</th>
                                <th class="px-5 py-4 font-bold text-gray-700 text-center w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($product->variants as $variant)
                                <tr class="hover:bg-gray-50/50 transition-all duration-300 ease-in-out">
                                    <td class="px-5 py-4 font-bold text-gray-900">{{ $variant->size }} - {{ $variant->color }}
                                    </td>
                                    <td class="px-5 py-4 text-center text-gray-600 font-mono text-xs font-medium">
                                        {{ $variant->sku }}
                                    </td>
                                    <td class="px-5 py-4 text-center text-gray-600 font-mono text-xs font-medium">
                                        {{ $variant->barcode ?? '-' }}
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span
                                            class="inline-flex items-center justify-center bg-gray-100 border border-gray-200 text-gray-700 font-bold px-3 py-1 rounded-md">{{ $this->getCurrentStock($variant->id) }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <input type="number" min="0" wire:model="addQuantities.{{ $variant->id }}"
                                            wire:keydown.enter.prevent=""
                                            class="w-full text-center px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none bg-gray-50 focus:bg-white text-gray-900 font-bold transition-all duration-300 ease-in-out">
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <button type="button" x-data
                                            @click="confirmDeletion(() => $wire.deleteVariant({{ $variant->id }}), 'Varian {{ $variant->size }}-{{ $variant->color }}')"
                                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all duration-300 ease-in-out"
                                            title="Hapus Varian">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            </form>
        @else
            <!-- Form Input Stok Tunggal -->
            <form id="stockForm" wire:submit="save">
                <div class="max-w-md">
                    <div
                        class="flex items-center justify-between p-5 bg-gray-50 rounded-xl border border-gray-200 mb-5 shadow-sm">
                        <span class="text-gray-700 font-bold">Stok Saat Ini</span>
                        <span class="text-2xl font-black text-gray-900 tracking-tight">{{ $this->getCurrentStock() }}</span>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Jumlah Stok Masuk (Baru)</label>
                        <input type="number" min="0" wire:model="addQuantities.single" wire:keydown.enter.prevent=""
                            class="w-full px-5 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none bg-gray-50 focus:bg-white text-gray-900 font-black text-xl transition-all duration-300 ease-in-out shadow-sm"
                            placeholder="0">
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>