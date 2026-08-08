<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Storage;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public Product $product;

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
    public $is_active = 1;

    #[Rule('nullable|image|max:5120', message: 'Foto harus berupa gambar (JPG/PNG) maksimal 5MB.')]
    public $photo = null;

    public $currentImage = null; // path foto yang sudah tersimpan
    public $removeCurrentPhoto = false;

    public function mount(Product $product)
    {
        $this->product = $product;

        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->brand_id = $product->brand_id;
        $this->cost_price = $product->cost_price;
        $this->sell_price = $product->sell_price;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode;
        $this->is_active = $product->is_active;
        $this->currentImage = $product->image;
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }

    public function removePhoto()
    {
        $this->photo = null;
    }

    public function removeSavedPhoto()
    {
        $this->removeCurrentPhoto = true;
        $this->currentImage = null;
    }

    public function save()
    {
        $this->validate();

        $imagePath = $this->product->image;

        // Ada foto baru di-upload → ganti
        if ($this->photo) {
            if ($this->product->image) {
                Storage::disk('public')->delete($this->product->image);
            }
            $imagePath = $this->photo->store('products', 'public');
        }
        // User sengaja hapus foto tanpa ganti baru
        elseif ($this->removeCurrentPhoto) {
            if ($this->product->image) {
                Storage::disk('public')->delete($this->product->image);
            }
            $imagePath = null;
        }

        $this->product->update([
            'name' => $this->name,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id ?: null,
            'cost_price' => $this->cost_price ?: 0,
            'sell_price' => $this->sell_price,
            'sku' => empty($this->sku) ? null : $this->sku,
            'barcode' => empty($this->barcode) ? null : $this->barcode,
            'image' => $imagePath,
            'is_active' => $this->is_active,
        ]);

        $this->redirect('/products', navigate: true);
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
                    class="flex-1 sm:flex-none flex items-center justify-center px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition">
                    Batal
                </a>

                <button type="submit"
                    class="flex-1 sm:flex-none flex items-center justify-center px-5 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition shadow-sm shadow-primary/30"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Stok Masuk</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">

                <!-- Informasi Dasar -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold mb-4">Informasi Dasar</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span
                                    class="text-red-500">*</span></label>
                            <input type="text" wire:model="name"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                            @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span
                                        class="text-red-500">*</span></label>
                                <select wire:model="category_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white">
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
                                <select wire:model="brand_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white">
                                    <option value="">Pilih Brand</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Harga & SKU -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold mb-4">Harga & SKU</h2>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <input type="number" wire:model="cost_price"
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual <span
                                        class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <input type="number" wire:model="sell_price"
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                                @error('sell_price') <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        @if(!$product->has_variants)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode SKU</label>
                                    <input type="text" wire:model="sku"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Barcode</label>
                                    <input type="text" wire:model="barcode"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                                </div>
                            </div>
                        @else
                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-sm text-blue-700">
                                Produk ini punya varian — SKU & barcode dikelola per varian, bukan di sini.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Foto Produk -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold mb-4">Foto Produk</h2>

                    @if($photo)
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
                    @elseif($currentImage)
                        <div class="relative rounded-xl overflow-hidden border border-gray-200">
                            <img src="{{ Storage::url($currentImage) }}" class="w-full h-48 object-cover">
                            <button type="button" wire:click="removeSavedPhoto"
                                class="absolute top-2 right-2 bg-white/90 hover:bg-white text-red-600 rounded-full p-1.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <label
                            class="mt-3 flex items-center justify-center px-4 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg font-medium text-sm hover:bg-blue-100 transition cursor-pointer w-full">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Ganti Foto
                            <input type="file" wire:model="photo" accept="image/*" class="hidden">
                        </label>
                    @else
                        <label
                            class="border-2 border-dashed border-indigo-200 bg-indigo-50/30 rounded-xl p-6 text-center block cursor-pointer hover:bg-indigo-50/50 transition">
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
                    @error('photo') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror
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