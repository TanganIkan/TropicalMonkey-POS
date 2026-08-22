<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Stock;
use App\Models\StockHistory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    #[Rule('required|min:3', message: 'Nama produk minimal 3 karakter.')]
    public $name = '';

    #[Rule('required', message: 'Kategori wajib dipilih.')]
    public $category_id = '';

    #[Rule('nullable|exists:brands,id', message: 'Brand tidak valid.')]
    public $brand_id = '';

    public $cost_price = null;

    #[Rule('required|numeric|min:0', message: 'Harga jual wajib diisi dengan angka.')]
    public $sell_price = null;

    public $sku = '';
    public $barcode = '';
    public $hasVariants = false;
    public $is_active = 1;

    #[Rule('nullable|image|max:5120', message: 'Foto harus berupa gambar (JPG/PNG) maksimal 5MB.')]
    public $photo = null;

    // ==== Varian ====
    public $availableSizes = ['S', 'M', 'L', 'XL', 'XXL'];
    public $selectedSizes = [];
    public $availableColors = [];
    public $newColor = '';
    public $variantStocks = [];

    // ==== Stok non-varian ====
    public $simpleStocks = [];

    public function addColor()
    {
        $color = trim($this->newColor);
        if ($color !== '' && !in_array($color, $this->availableColors)) {
            $this->availableColors[] = $color;
        }
        $this->newColor = '';
    }

    public function removeColor($color)
    {
        $this->availableColors = array_values(array_diff($this->availableColors, [$color]));
    }

    public function getVariantCombinationsProperty()
    {
        if (empty($this->selectedSizes) || empty($this->availableColors)) {
            return [];
        }

        $combinations = [];
        foreach ($this->selectedSizes as $size) {
            foreach ($this->availableColors as $color) {
                $combinations[] = ['size' => $size, 'color' => $color];
            }
        }

        return $combinations;
    }

    public function getActiveOutletProperty()
    {
        return Outlet::find(session('current_outlet_id'));
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }

    public function generateSku()
    {
        if (empty(trim($this->name)) || empty($this->category_id)) {
            $this->dispatch('swal', [
                'title' => 'Data Belum Lengkap!',
                'text' => 'Isi Nama Produk dan pilih Kategori terlebih dahulu untuk men-generate SKU Auto.',
                'icon' => 'warning'
            ]);
            return;
        }

        $category = Category::find($this->category_id);
        $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category->name), 0, 3));

        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->name), 0, 3));

        $randomNumber = mt_rand(1000, 9999);

        $this->sku = $categoryCode . '-' . $nameCode . '-' . $randomNumber;
    }

    public function handleBarcodeScan()
    {
        if (!empty($this->barcode) && empty($this->sku)) {
            $this->sku = $this->barcode;
        }
    }

    public function removePhoto()
    {
        $this->photo = null;
    }

    public function save()
    {
        $this->validate();

        if ($this->hasVariants && empty($this->variantCombinations)) {
            $this->dispatch('swal', [
                'title' => 'Varian Kosong!',
                'text' => 'Pilih minimal 1 ukuran dan 1 warna untuk membuat varian.',
                'icon' => 'warning'
            ]);
            return;
        }

        $activeOutletId = session('current_outlet_id');
        $productName = trim($this->name);

        $existingProduct = Product::where('name', $productName)->first();

        if ($existingProduct) {
            if (!$this->hasVariants) {
                $this->dispatch('swal', [
                    'title' => 'Produk Sudah Terdaftar!',
                    'text' => 'Bedakan namanya, atau aktifkan "Ada Opsi Lain" untuk menambah varian ke produk ini.',
                    'icon' => 'error'
                ]);
                return;
            }

            if ($existingProduct->category_id != $this->category_id) {
                $this->dispatch('swal', [
                    'title' => 'Kategori Berbeda!',
                    'text' => "Produk '{$productName}' sebelumnya didaftarkan di kategori '{$existingProduct->category->name}'. Kategori harus disamakan.",
                    'icon' => 'error'
                ]);
                return;
            }

            $brandInput = $this->brand_id ?: null;
            if ($existingProduct->brand_id != $brandInput) {
                $this->dispatch('swal', [
                    'title' => 'Brand Berbeda!',
                    'text' => "Brand tidak cocok dengan produk induk '{$productName}' yang sudah ada sebelumnya.",
                    'icon' => 'error'
                ]);
                return;
            }
        }

        DB::transaction(function () use ($activeOutletId, $productName, $existingProduct) {
            $imagePath = $this->photo ? $this->photo->store('products', 'public') : null;

            if ($existingProduct) {
                $product = $existingProduct;

                if ($this->hasVariants && !$product->has_variants) {
                    $product->update(['has_variants' => true]);
                }

                if ($imagePath) {
                    $product->update(['image' => $imagePath]);
                }
            } else {
                $product = Product::create([
                    'name' => $productName,
                    'category_id' => $this->category_id,
                    'brand_id' => $this->brand_id ?: null,
                    'cost_price' => $this->cost_price ?: 0,
                    'sell_price' => $this->sell_price,
                    'sku' => $this->hasVariants ? null : (empty($this->sku) ? null : $this->sku),
                    'barcode' => $this->hasVariants ? null : (empty($this->barcode) ? null : $this->barcode),
                    'image' => $imagePath,
                    'has_variants' => $this->hasVariants,
                    'is_active' => $this->is_active,
                ]);
            }

            $outlets = Outlet::where('is_active', true)->get();

            if ($this->hasVariants) {
                foreach ($this->variantCombinations as $combo) {
                    $key = $combo['size'] . '|' . $combo['color'];

                    $existingVariant = ProductVariant::where('product_id', $product->id)->where('size', $combo['size'])->where('color', $combo['color'])->first();

                    if (!$existingVariant) {
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => strtoupper(Str::slug($product->name, '-')) . '-' . strtoupper($combo['size']) . '-' . strtoupper(Str::slug($combo['color'], '-')),
                            'size' => $combo['size'],
                            'color' => $combo['color'],
                        ]);

                        foreach ($outlets as $outlet) {
                            $qty = $outlet->id == $activeOutletId ? $this->variantStocks[$key][$outlet->id] ?? 0 : 0;

                            if ($qty > 0) {
                                StockHistory::create([
                                    'product_id' => $product->id,
                                    'product_variant_id' => $variant->id,
                                    'outlet_id' => $outlet->id,
                                    'quantity' => $qty,
                                    'type' => 'in',
                                    'nota_number' => null,
                                ]);
                            }

                            Stock::create([
                                'product_id' => $product->id,
                                'product_variant_id' => $variant->id,
                                'outlet_id' => $outlet->id,
                                'quantity' => $qty,
                            ]);
                        }
                    }
                }
            } else {
                if (!$existingProduct) {
                    foreach ($outlets as $outlet) {
                        $qty = $outlet->id == $activeOutletId ? $this->simpleStocks[$outlet->id] ?? 0 : 0;

                        if ($qty > 0) {
                            StockHistory::create([
                                'product_id' => $product->id,
                                'product_variant_id' => null,
                                'outlet_id' => $outlet->id,
                                'quantity' => $qty,
                                'type' => 'in',
                                'nota_number' => null,
                            ]);
                        }

                        Stock::create([
                            'product_id' => $product->id,
                            'product_variant_id' => null,
                            'outlet_id' => $outlet->id,
                            'quantity' => $qty,
                        ]);
                    }
                }
            }
        });

        $this->dispatch('product-saved');
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen" x-data
    @product-saved.window="
        Swal.fire({
            title: 'Berhasil Dibuat!',
            text: 'Data produk baru berhasil disimpan ke sistem.',
            icon: 'success',
            confirmButtonColor: '#111827', /* Selaras dengan tema primary hitam */
            heightAuto: false,
            scrollbarPadding: false
        }).then(() => {
            Livewire.navigate('/products');
        })
    ">

    <form wire:submit="save">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 md:mb-8 gap-4">
            <div>
                <a href="/products" wire:navigate
                    class="text-sm text-gray-500 hover:text-gray-900 flex items-center mb-2 transition-all duration-300 ease-in-out font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali ke Data Produk
                </a>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Tambah Produk Baru</h1>
            </div>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <a href="/products" wire:navigate
                    class="flex-1 sm:flex-none text-center px-6 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-50 hover:border-gray-300 transition-all duration-300 ease-in-out shadow-sm">
                    Batal
                </a>
                <button type="submit"
                    class="flex-1 sm:flex-none flex items-center justify-center px-6 py-2.5 bg-gray-900 text-white rounded-lg font-bold hover:bg-black transition-all duration-300 ease-in-out shadow-lg shadow-gray-900/20">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8 items-start">
            <!-- Kolom Kiri -->
            <div class="lg:col-span-2 space-y-6 md:space-y-8">

                <!-- Basic Information -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <div class="flex items-center mb-5 text-gray-900">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h2 class="text-lg font-bold">Informasi Dasar</h2>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Produk <span
                                    class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" placeholder="mis. Kaos Polos Hitam"
                                class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out">
                            @error('name')
                                <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Kategori <span
                                        class="text-red-500">*</span></label>
                                <select wire:model="category_id"
                                    class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-medium text-gray-800">
                                    <option value="">Pilih Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Merek (Brand)</label>
                                <select wire:model="brand_id"
                                    class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-medium text-gray-800">
                                    <option value="">Pilih Brand</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id')
                                    <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Deskripsi</label>
                            <textarea rows="4" placeholder="Tuliskan deskripsi atau detail produk di sini..."
                                class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Inventory -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <div class="flex items-center mb-5 text-gray-900">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                            </path>
                        </svg>
                        <h2 class="text-lg font-bold">Harga & SKU</h2>
                    </div>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 sm:items-end">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Harga Modal</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-gray-500 font-bold">Rp</span>
                                    <input type="number" wire:model.live.debounce.500ms="cost_price" placeholder="0"
                                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-bold">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Harga Jual <span
                                        class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-gray-500 font-bold">Rp</span>
                                    <input type="number" wire:model.live.debounce.500ms="sell_price" placeholder="0"
                                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-bold text-gray-900">
                                </div>
                                @error('sell_price')
                                    <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Premium Margin Box -->
                            <div
                                class="bg-gray-100 border border-gray-200 p-2.5 rounded-lg flex flex-col items-center justify-center mt-2 sm:mt-0 shadow-sm">
                                <span class="block text-xs font-bold text-gray-500 mb-0.5">Est. Margin</span>
                                <span class="font-black text-lg text-gray-900 tracking-tight">
                                    @if ($cost_price > 0 && $sell_price > $cost_price)
                                        {{ round((($sell_price - $cost_price) / $cost_price) * 100) }}%
                                    @elseif($sell_price > 0 && empty($cost_price))
                                        100%
                                    @else
                                        --%
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Kode SKU</label>
                                <div
                                    class="flex shadow-sm rounded-lg overflow-hidden border border-gray-200 focus-within:ring-2 focus-within:ring-gray-900 focus-within:border-transparent transition-all duration-300 ease-in-out">
                                    <input type="text" wire:model="sku" placeholder="mis. KOS-HTM-M"
                                        class="flex-1 w-full px-4 py-2.5 bg-gray-50 focus:bg-white outline-none font-mono text-sm">
                                    <button type="button" wire:click="generateSku"
                                        class="px-4 bg-gray-100 text-gray-700 border-l border-gray-200 font-bold text-xs hover:bg-gray-200 transition-all duration-300 uppercase tracking-wider">
                                        Auto
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Barcode (Opsional)</label>
                                <input type="text" wire:model="barcode" wire:keydown.enter.prevent="handleBarcodeScan"
                                    placeholder="Klik di sini, tembak scanner..."
                                    class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300 ease-in-out font-mono">
                                <p class="text-[10px] text-gray-400 mt-1.5 font-medium">*Klik kolom lalu tembak scanner
                                    barcode
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Variants Area -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center text-gray-900">
                            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                </path>
                            </svg>
                            <h2 class="text-lg font-bold">Varian Produk</h2>
                        </div>

                        <!-- Toggle Premium -->
                        <label class="flex items-center cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" wire:model.live="hasVariants" class="hidden">
                                <div
                                    class="block w-11 h-6 rounded-full transition-all duration-300 ease-in-out shadow-inner {{ $hasVariants ? 'bg-gray-900' : 'bg-gray-200' }}">
                                </div>
                                <div
                                    class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 ease-in-out shadow-sm {{ $hasVariants ? 'transform translate-x-5' : '' }}">
                                </div>
                            </div>
                            <div class="ml-3 text-sm text-gray-700 font-bold group-hover:text-gray-900 transition">Ada
                                Opsi Lain</div>
                        </label>
                    </div>

                    @error('hasVariants')
                        <span class="text-red-500 text-xs mt-2 block font-medium">{{ $message }}</span>
                    @enderror

                    @if ($hasVariants)
                        <div class="mt-5 space-y-6 pt-5 border-t border-gray-100" wire:key="variant-section">
                            <div wire:key="size-picker">
                                <label class="block text-sm font-bold text-gray-700 mb-3">Pilih Ukuran</label>
                                <div class="flex flex-wrap gap-2.5">
                                    @foreach ($availableSizes as $size)
                                        <label for="ukuran-{{ $size }}" class="cursor-pointer" wire:key="size-{{ $size }}">
                                            <input type="checkbox" id="ukuran-{{ $size }}" wire:model.live="selectedSizes"
                                                value="{{ $size }}" class="hidden peer">
                                            <span
                                                class="px-5 py-2 rounded-lg border-2 text-sm font-bold block border-gray-200 text-gray-500 peer-checked:border-gray-900 peer-checked:text-gray-900 peer-checked:bg-gray-900/5 transition-all duration-200 ease-in-out hover:border-gray-300">
                                                {{ $size }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-3">Tambah Warna</label>
                                <div class="flex gap-2 mb-3">
                                    <input type="text" wire:model="newColor" wire:keydown.enter.prevent="addColor"
                                        placeholder="Ketik warna (mis. Hitam), lalu tekan Enter"
                                        class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm outline-none focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300">
                                    <button type="button" wire:click="addColor"
                                        class="px-5 py-2.5 bg-gray-900 text-white rounded-lg text-sm font-bold hover:bg-black transition-all duration-300 shadow-sm">Tambah</button>
                                </div>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    @foreach ($availableColors as $color)
                                        <span wire:key="color-{{ $color }}"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gray-100 border border-gray-200 text-gray-800 font-bold rounded-lg text-sm shadow-sm transition">
                                            {{ $color }}
                                            <button type="button" wire:click="removeColor('{{ $color }}')"
                                                class="text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md p-0.5 transition">&times;</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            @if (count($this->variantCombinations) > 0)
                                <div class="border border-gray-200 rounded-xl overflow-hidden mt-6 shadow-sm"
                                    wire:key="combo-table">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-bold text-gray-700">Ukuran</th>
                                                <th class="px-4 py-3 text-left font-bold text-gray-700">Warna</th>
                                                <th class="px-4 py-3 text-center font-bold text-gray-700">
                                                    Stok Awal <br><span
                                                        class="text-[10px] font-medium text-gray-500">({{ $this->activeOutlet->name ?? 'Outlet Aktif' }})</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($this->variantCombinations as $combo)
                                                @php $key = $combo['size'] . '|' . $combo['color']; @endphp
                                                <tr wire:key="combo-{{ $key }}" class="hover:bg-gray-50/50 transition">
                                                    <td class="px-4 py-3 font-bold text-gray-900">{{ $combo['size'] }}</td>
                                                    <td class="px-4 py-3 font-bold text-gray-900">{{ $combo['color'] }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        <input type="number" min="0"
                                                            wire:model="variantStocks.{{ $key }}.{{ session('current_outlet_id') }}"
                                                            placeholder="0"
                                                            class="w-24 text-center px-3 py-1.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent font-bold transition">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="bg-gray-50 border border-gray-200 border-dashed rounded-xl p-6 text-center mt-6">
                                    <p class="text-sm font-bold text-gray-500">Belum Ada Kombinasi</p>
                                    <p class="text-xs text-gray-400 mt-1">Pilih minimal 1 ukuran dan tambahkan 1 warna.</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="mt-6 pt-5 border-t border-gray-100">
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 flex items-center justify-between">
                                Stok Awal
                                <span
                                    class="text-[10px] font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded uppercase tracking-wider">{{ $this->activeOutlet->name ?? 'Outlet Aktif' }}</span>
                            </label>
                            <input type="number" min="0" wire:model="simpleStocks.{{ session('current_outlet_id') }}"
                                placeholder="0"
                                class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent font-bold text-lg transition-all duration-300 ease-in-out">
                        </div>
                    @endif
                </div>

            </div>

            <!-- Kolom Kanan -->
            <div class="space-y-6 md:space-y-8">
                <!-- Media Upload -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <div class="flex items-center mb-5 text-gray-900">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <h2 class="text-lg font-bold">Foto Produk</h2>
                    </div>

                    @if ($photo)
                        <!-- Preview foto -->
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 shadow-sm group">
                            <img src="{{ $photo->temporaryUrl() }}"
                                class="w-full h-56 object-cover transition-all duration-300 ease-in-out group-hover:scale-105">
                            <button type="button" wire:click="removePhoto"
                                class="absolute top-3 right-3 bg-white hover:bg-red-50 text-gray-400 hover:text-red-600 rounded-full p-2 shadow-sm transition-all duration-300 ease-in-out border border-gray-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @else
                        <!-- Area upload -->
                        <label
                            class="border-2 border-dashed border-gray-300 bg-gray-50/50 rounded-xl p-8 text-center block cursor-pointer hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 ease-in-out group">
                            <div
                                class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-gray-200 group-hover:scale-110 transition-all duration-300">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-800">Klik atau seret gambar</p>
                            <p class="text-xs text-gray-500 mt-1 mb-5">PNG, JPG (maks. 5MB)</p>
                            <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            <span
                                class="inline-block px-5 py-2 bg-gray-900 text-white rounded-lg text-xs font-bold hover:bg-black transition-all duration-300 shadow-sm">
                                Pilih File
                            </span>
                        </label>
                    @endif

                    <div wire:loading wire:target="photo"
                        class="text-xs font-bold text-gray-500 mt-3 flex justify-center items-center gap-2">
                        <svg class="animate-spin h-3 w-3 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Mengunggah...
                    </div>
                    @error('photo')
                        <span class="text-red-500 text-xs mt-2 block font-medium">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Status -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Visibilitas Sistem</h2>
                    <select wire:model="is_active"
                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-900 font-bold focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none mb-3 transition-all duration-300 ease-in-out cursor-pointer">
                        <option value="1">Aktif & Ditampilkan</option>
                        <option value="0">Draf (Disembunyikan)</option>
                    </select>
                    <p class="text-xs text-gray-500 font-medium">Hanya produk "Aktif" yang bisa di-scan/dicari di
                        halaman Kasir.</p>
                </div>
            </div>
        </div>
    </form>
</div>