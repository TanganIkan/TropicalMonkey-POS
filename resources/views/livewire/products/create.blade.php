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
    public $variantStocks = []; // ["S|Hitam" => [outlet_id => qty]]

    // ==== Stok non-varian ====
    public $simpleStocks = []; // [outlet_id => qty]

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
            $this->addError('sku', 'Isi Nama Produk dan pilih Kategori terlebih dahulu untuk men-generate SKU Auto.');
            return;
        }

        $category = Category::find($this->category_id);
        $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category->name), 0, 3));

        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->name), 0, 3));

        $randomNumber = mt_rand(1000, 9999);

        $this->sku = $categoryCode . '-' . $nameCode . '-' . $randomNumber;

        $this->resetErrorBag('sku');
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
            $this->addError('hasVariants', 'Pilih minimal 1 ukuran dan 1 warna untuk membuat varian.');
            return;
        }

        $activeOutletId = session('current_outlet_id');
        $productName = trim($this->name);

        $existingProduct = Product::where('name', $productName)->first();

        $existingProduct = Product::where('name', $productName)->first();

        if ($existingProduct) {
            if (!$this->hasVariants) {
                $this->addError('name', 'Produk dengan nama ini sudah terdaftar! Bedakan namanya, atau aktifkan "Ada opsi lain" untuk menambah ukuran/warna baru ke produk ini.');
                return;
            }

            // Validasi Kategori
            if ($existingProduct->category_id != $this->category_id) {
                $this->addError('category_id', "Produk '{$productName}' sudah ada di kategori '{$existingProduct->category->name}'. Kategori harus sama jika ingin menambah varian.");
                return;
            }

            // Validasi Brand
            $brandInput = $this->brand_id ?: null;
            if ($existingProduct->brand_id != $brandInput) {
                $this->addError('brand_id', "Brand tidak cocok dengan produk induk '{$productName}' yang sudah ada sebelumnya.");
                return;
            }
        }

        DB::transaction(function () use ($activeOutletId, $productName, $existingProduct) {
            $imagePath = $this->photo ? $this->photo->store('products', 'public') : null;

            // 3. GUNAKAN PRODUK LAMA ATAU BUAT BARU
            if ($existingProduct) {
                $product = $existingProduct;

                // Jika produk lama awalnya "Tunggal", sekarang kita update jadi "Bervarian"
                if ($this->hasVariants && !$product->has_variants) {
                    $product->update(['has_variants' => true]);
                }

                // Update foto induk jika ada foto baru
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

            // 4. BUAT VARIAN (Jika ada)
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
                // 5. BUAT STOK UNTUK PRODUK TUNGGAL (Hanya jika produknya benar-benar baru)
                if (!$existingProduct) {
                    foreach ($outlets as $outlet) {
                        $qty = $outlet->id == $activeOutletId ? $this->simpleStocks[$outlet->id] ?? 0 : 0;

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

        session()->flash('success', 'Data produk berhasil disimpan!');
        $this->redirect('/products', navigate: true);
    }
};
?>

<div class="p-4 md:p-6 flex flex-col space-y-4 md:space-y-6">
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

        <!-- Main Form Grid (SUDAH DITAMBAHKAN items-start DI SINI) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

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
                            @error('name')
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span
                                        class="text-red-500">*</span></label>
                                <select wire:model="category_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition bg-white">
                                    <option value="">Pilih Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Merek (Brand)</label>
                                <select wire:model="brand_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition bg-white">
                                    <option value="">Pilih Brand</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
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
                                @error('sell_price')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="bg-indigo-50 border border-indigo-100 p-2 rounded-lg text-center mt-2 sm:mt-0">
                                <span class="block text-xs text-indigo-800 mb-1">Est. Keuntungan</span>
                                <!-- Logika Perhitungan Margin Otomatis -->
                                <span class="font-bold text-indigo-900">
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
                                <input type="checkbox" wire:model.live="hasVariants" class="hidden">
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

                    @error('hasVariants')
                        <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span>
                    @enderror

                    @if ($hasVariants)
                        <div class="mt-4 space-y-4" wire:key="variant-section">
                            <div wire:key="size-picker">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Ukuran</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($availableSizes as $size)
                                        <label for="ukuran-{{ $size }}" class="cursor-pointer" wire:key="size-{{ $size }}">
                                            <input type="checkbox" id="ukuran-{{ $size }}" wire:model.live="selectedSizes"
                                                value="{{ $size }}" class="hidden peer">
                                            <span
                                                class="px-4 py-2 rounded-lg border-2 text-sm font-medium block border-gray-200 text-gray-500 peer-checked:border-primary peer-checked:text-primary peer-checked:bg-primary/5 transition">
                                                {{ $size }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Warna</label>
                                <div class="flex gap-2 mb-2">
                                    <input type="text" wire:model="newColor" wire:keydown.enter.prevent="addColor"
                                        placeholder="mis. Hitam, lalu Enter"
                                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary">
                                    <button type="button" wire:click="addColor"
                                        class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90">Tambah</button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($availableColors as $color)
                                        <span wire:key="color-{{ $color }}"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 rounded-full text-sm">
                                            {{ $color }}
                                            <button type="button" wire:click="removeColor('{{ $color }}')"
                                                class="text-gray-400 hover:text-red-500">&times;</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            @if (count($this->variantCombinations) > 0)
                                <div class="border border-gray-200 rounded-lg overflow-hidden mt-4" wire:key="combo-table">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Ukuran</th>
                                                <th class="px-3 py-2 text-left">Warna</th>
                                                <th class="px-3 py-2 text-center">
                                                    Stok - {{ $this->activeOutlet->name ?? 'Outlet Aktif' }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($this->variantCombinations as $combo)
                                                @php $key = $combo['size'] . '|' . $combo['color']; @endphp
                                                <tr wire:key="combo-{{ $key }}">
                                                    <td class="px-3 py-2">{{ $combo['size'] }}</td>
                                                    <td class="px-3 py-2">{{ $combo['color'] }}</td>
                                                    <td class="px-3 py-2 text-center">
                                                        <input type="number" min="0"
                                                            wire:model="variantStocks.{{ $key }}.{{ session('current_outlet_id') }}"
                                                            placeholder="0"
                                                            class="w-24 text-center px-2 py-1 border border-gray-200 rounded outline-none focus:ring-2 focus:ring-primary">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-400 text-center py-4">Pilih minimal 1 ukuran dan 1 warna
                                    untuk melihat kombinasi varian.</p>
                            @endif
                        </div>
                    @else
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Stok Awal - {{ $this->activeOutlet->name ?? 'Outlet Aktif' }}
                            </label>
                            <input type="number" min="0" wire:model="simpleStocks.{{ session('current_outlet_id') }}"
                                placeholder="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-primary">
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

                    @if ($photo)
                        <!-- Preview foto yang sudah dipilih -->
                        <div class="relative rounded-xl overflow-hidden border border-gray-200">
                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-48 object-cover">
                            <button type="button" wire:click="removePhoto"
                                class="absolute top-2 right-2 bg-white/90 hover:bg-white text-red-600 rounded-full p-1.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @else
                        <!-- Area upload -->
                        <label
                            class="border-2 border-dashed border-indigo-200 bg-indigo-50/30 rounded-xl p-6 text-center block cursor-pointer hover:bg-indigo-50/50 transition">
                            <div
                                class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-indigo-100">
                                <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-800">Klik atau seret gambar</p>
                            <p class="text-xs text-gray-500 mt-1 mb-4">PNG, JPG (maks. 5MB)</p>
                            <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            <span
                                class="inline-block px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-200 transition">
                                Pilih File
                            </span>
                        </label>
                    @endif

                    <div wire:loading wire:target="photo" class="text-xs text-gray-500 mt-2 text-center">Mengunggah...
                    </div>
                    @error('photo')
                        <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span>
                    @enderror
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