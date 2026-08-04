<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str; // Tambahkan ini untuk pembuat kode acak

new #[Layout('components.layouts.app')] class extends Component {
    #[Rule('required|min:3', message: 'Nama produk minimal 3 karakter.')]
    public $name = '';

    #[Rule('required', message: 'Kategori wajib dipilih.')]
    public $category_id = '';

    public $brand = '';

    // Default kita buat null agar kosong saat pertama kali dibuka
    public $cost_price = null;

    #[Rule('required|numeric|min:0', message: 'Harga jual wajib diisi dengan angka.')]
    public $sell_price = null;

    public $sku = '';
    public $barcode = '';
    public $hasVariants = false;
    public $is_active = 1;

    public function with(): array
    {
        return [
            'categories' => Category::all()
        ];
    }

    // Fungsi untuk membuat SKU otomatis
    public function generateSku()
    {
        // Format: VLV-TahunBulan-HurufAcak (misal: VLV-2608-AXYZ)
        $this->sku = 'MLT-' . date('ym') . '-' . strtoupper(Str::random(4));
    }

    public function handleBarcodeScan()
    {
        // Karena scanner otomatis "enter" setelah scan, 
        // ini bisa dipakai buat validasi/auto-generate SKU dari barcode
        if (!empty($this->barcode)) {
            // contoh: auto isi SKU dari barcode kalau SKU masih kosong
            if (empty($this->sku)) {
                $this->sku = $this->barcode;
            }
        }
    }

    public function save()
    {
        $this->validate();

        Product::create([
            'name' => $this->name,
            'category_id' => $this->category_id,
            'brand' => $this->brand,
            'cost_price' => $this->cost_price ?: 0,
            'sell_price' => $this->sell_price,
            'sku' => empty($this->sku) ? null : $this->sku,
            'barcode' => empty($this->barcode) ? null : $this->barcode,
            'has_variants' => $this->hasVariants,
            'is_active' => $this->is_active,
        ]);

        $this->redirect('/products', navigate: true);
    }
};
?>

<div class="mx-auto pb-10">
    <!-- Ubah pembungkus menjadi tag FORM -->
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
                <h1 class="text-2xl font-bold text-gray-900">Tambah Produk Baru</h1>
            </div>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <a href="/products" wire:navigate
                    class="flex-1 sm:flex-none text-center px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">Batal</a>
                <!-- Ubah jadi tombol submit -->
                <button type="submit"
                    class="flex-1 sm:flex-none px-5 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition shadow-sm shadow-primary/30 flex items-center justify-center">
                    <span wire:loading.remove wire:target="save">Simpan Produk</span>
                    <span wire:loading wire:target="save">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Menyimpan...
                    </span>
                </button>
            </div>
        </div>

        <!-- Main Form Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kolom Kiri -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Basic Information -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="flex items-center mb-4 text-gray-800">
                        <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h2 class="text-lg font-bold">Informasi Dasar</h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span
                                    class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" placeholder="mis. Kaos Polos Hitam"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                            @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span
                                        class="text-red-500">*</span></label>
                                <select wire:model="category_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition bg-white">
                                    <option value="">Pilih Kategori</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Merek (Brand)</label>
                                <input type="text" wire:model="brand" placeholder="mis. Velvet Basics"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                            <textarea rows="4" placeholder="Tuliskan deskripsi atau detail produk di sini..."
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition bg-gray-50"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="flex items-center mb-4 text-gray-800">
                        <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                            </path>
                        </svg>
                        <h2 class="text-lg font-bold">Harga & SKU</h2>
                    </div>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <!-- Gunakan wire:model.live.debounce -->
                                    <input type="number" wire:model.live.debounce.500ms="cost_price" placeholder="0"
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual <span
                                        class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <!-- Gunakan wire:model.live.debounce -->
                                    <input type="number" wire:model.live.debounce.500ms="sell_price" placeholder="0"
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                                @error('sell_price') <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="bg-indigo-50 border border-indigo-100 p-2 rounded-lg text-center mt-2 sm:mt-0">
                                <span class="block text-xs text-indigo-800 mb-1">Est. Keuntungan</span>
                                <!-- Logika Perhitungan Margin Otomatis -->
                                <span class="font-bold text-indigo-900">
                                    @if($cost_price > 0 && $sell_price > $cost_price)
                                        {{ round((($sell_price - $cost_price) / $cost_price) * 100) }}%
                                    @elseif($sell_price > 0 && empty($cost_price))
                                        100%
                                    @else
                                        --%
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kode SKU</label>
                                <div class="flex">
                                    <input type="text" wire:model="sku" placeholder="mis. KOS-HTM-M"
                                        class="flex-1 w-full px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-primary outline-none border-r-0">
                                    <!-- Tambahkan wire:click="generateSku" di sini -->
                                    <button type="button" wire:click="generateSku"
                                        class="px-3 bg-blue-50 text-blue-700 border border-blue-200 rounded-r-lg font-medium text-sm hover:bg-blue-100 transition">Auto</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barcode (Opsional)</label>
                                <input type="text" wire:model="barcode" wire:keydown.enter.prevent="handleBarcodeScan"
                                    placeholder="Klik di sini, lalu tembak scanner..."
                                    class="flex-1 w-full px-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary">
                                <p class="text-[10px] text-gray-400 mt-1">*Klik kolom ini lalu tembak scanner barcode
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Variants Area -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-gray-800">
                            <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                </path>
                            </svg>
                            <h2 class="text-lg font-bold">Varian Produk</h2>
                        </div>
                        <label class="flex items-center cursor-pointer">
                            <div class="relative">
                                <input type="checkbox" wire:model.live="hasVariants" class="sr-only">
                                <div
                                    class="block bg-gray-200 w-10 h-6 rounded-full transition {{ $hasVariants ? 'bg-primary' : '' }}">
                                </div>
                                <div
                                    class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition {{ $hasVariants ? 'transform translate-x-4' : '' }}">
                                </div>
                            </div>
                            <div class="ml-2 sm:ml-3 text-xs sm:text-sm text-gray-700 font-medium">Ada opsi lain</div>
                        </label>
                    </div>

                    @if($hasVariants)
                        <div
                            class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg text-center text-gray-500 text-sm">
                            Fitur pengaturan Varian Ukuran & Warna akan muncul di sini.
                        </div>
                    @endif
                </div>

            </div>

            <!-- Kolom Kanan -->
            <div class="space-y-6">
                <!-- Media Upload -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="flex items-center mb-4 text-gray-800">
                        <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <h2 class="text-lg font-bold">Foto Produk</h2>
                    </div>

                    <div class="border-2 border-dashed border-indigo-200 bg-indigo-50/30 rounded-xl p-6 text-center">
                        <div
                            class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-indigo-100">
                            <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-gray-800">Klik atau seret gambar</p>
                        <p class="text-xs text-gray-500 mt-1 mb-4">PNG, JPG (maks. 5MB)</p>
                        <button type="button"
                            class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-200 transition">Pilih
                            File</button>
                    </div>
                </div>

                <!-- Status -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Status Tampil</h2>
                    <select wire:model="is_active"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg text-green-700 font-medium focus:ring-2 focus:ring-primary outline-none bg-green-50 mb-3">
                        <option value="1">Aktif</option>
                        <option value="0">Draf (Disembunyikan)</option>
                    </select>
                    <p class="text-xs text-gray-500">Produk berstatus "Aktif" akan muncul di POS.</p>
                </div>
            </div>
        </div>
    </form>
</div>