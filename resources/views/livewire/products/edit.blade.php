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

    public $currentImage = null;
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

        if ($this->photo) {
            if ($this->product->image) {
                Storage::disk('public')->delete($this->product->image);
            }
            $imagePath = $this->photo->store('products', 'public');
        } elseif ($this->removeCurrentPhoto) {
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
            'image' => $imagePath,
            'is_active' => $this->is_active,
        ]);

        $this->dispatch('product-updated');
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen" x-data @product-updated.window="
        Swal.fire({
            title: 'Berhasil!',
            text: 'Data produk berhasil diperbarui.',
            icon: 'success',
            confirmButtonColor: '#111827',
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali ke Data Produk
                </a>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Edit Produk</h1>
            </div>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <a href="/products" wire:navigate
                    class="flex-1 sm:flex-none flex items-center justify-center px-6 py-2.5 bg-white border border-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-50 hover:border-gray-300 transition-all duration-300 ease-in-out shadow-sm">
                    Batal
                </a>

                <button type="submit"
                    class="flex-1 sm:flex-none flex items-center justify-center px-6 py-2.5 bg-gray-900 text-white rounded-lg font-bold hover:bg-black transition-all duration-300 ease-in-out shadow-lg shadow-gray-900/20"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
            <div class="lg:col-span-2 space-y-6 md:space-y-8">

                <!-- Informasi Dasar -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Informasi Dasar
                    </h2>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Produk <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="name"
                                class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out">
                            @error('name') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Kategori <span class="text-red-500">*</span></label>
                                <select wire:model="category_id"
                                    class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-medium text-gray-800">
                                    <option value="">Pilih Kategori</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Merek (Brand)</label>
                                <select wire:model="brand_id"
                                    class="w-full px-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-medium text-gray-800">
                                    <option value="">Pilih Brand</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                                @error('brand_id') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Harga & SKU -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Harga & Kode Item
                    </h2>
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Harga Modal</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-gray-500 font-bold">Rp</span>
                                    <input type="number" wire:model="cost_price"
                                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-bold">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">Harga Jual <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-3 text-gray-500 font-bold">Rp</span>
                                    <input type="number" wire:model="sell_price"
                                        class="w-full pl-11 pr-4 py-2.5 bg-gray-50 focus:bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent outline-none transition-all duration-300 ease-in-out font-bold text-gray-900">
                                </div>
                                @error('sell_price') <span class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        @if(!$product->has_variants)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1.5 flex justify-between">
                                        Kode SKU
                                        <span class="text-[10px] text-gray-400 font-normal mt-0.5 flex items-center gap-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            Terkunci
                                        </span>
                                    </label>
                                    <input type="text" wire:model="sku" disabled
                                        class="w-full px-4 py-2.5 border border-gray-200 bg-gray-100 text-gray-500 rounded-lg outline-none cursor-not-allowed font-mono text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1.5 flex justify-between">
                                        Barcode
                                        <span class="text-[10px] text-gray-400 font-normal mt-0.5 flex items-center gap-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                            Terkunci
                                        </span>
                                    </label>
                                    <input type="text" wire:model="barcode" disabled
                                        class="w-full px-4 py-2.5 border border-gray-200 bg-gray-100 text-gray-500 rounded-lg outline-none cursor-not-allowed font-mono text-sm">
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-600 flex items-start gap-3 mt-2">
                                <svg class="w-5 h-5 flex-shrink-0 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <div>
                                    <span class="font-bold text-gray-800">Produk Bervarian.</span> SKU dan Barcode dikelola secara spesifik pada masing-masing ukuran/warna di menu kelola stok.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-6 md:space-y-8">
                <!-- Foto Produk -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-5">Foto Produk</h2>

                    @if($photo)
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 shadow-sm group">
                            <img src="{{ $photo->temporaryUrl() }}" class="w-full h-56 object-cover transition-all duration-300 ease-in-out group-hover:scale-105">
                            <button type="button" wire:click="removePhoto"
                                class="absolute top-3 right-3 bg-white hover:bg-red-50 text-gray-400 hover:text-red-600 rounded-full p-2 shadow-sm transition-all duration-300 ease-in-out border border-gray-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    @elseif($currentImage)
                        <div class="relative rounded-xl overflow-hidden border border-gray-200 shadow-sm group">
                            <img src="{{ Storage::url($currentImage) }}" class="w-full h-56 object-cover transition-all duration-300 ease-in-out group-hover:scale-105">
                            <button type="button" wire:click="removeSavedPhoto"
                                class="absolute top-3 right-3 bg-white hover:bg-red-50 text-gray-400 hover:text-red-600 rounded-full p-2 shadow-sm transition-all duration-300 ease-in-out border border-gray-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <label
                            class="mt-4 flex items-center justify-center px-4 py-3 bg-white text-gray-700 border border-gray-200 rounded-xl font-bold text-sm hover:bg-gray-50 transition-all duration-300 ease-in-out cursor-pointer w-full shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Ganti Foto
                            <input type="file" wire:model="photo" accept="image/*" class="hidden">
                        </label>
                    @else
                        <label
                            class="border-2 border-dashed border-gray-300 bg-gray-50/50 rounded-xl p-8 text-center block cursor-pointer hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 ease-in-out group">
                            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-gray-200 group-hover:scale-110 transition-all duration-300">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-800">Klik atau seret gambar</p>
                            <p class="text-xs text-gray-500 mt-1 mb-5">PNG, JPG (maks. 5MB)</p>
                            <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            <span class="inline-block px-5 py-2 bg-gray-900 text-white rounded-lg text-xs font-bold hover:bg-black transition-all duration-300 shadow-sm">
                                Pilih File
                            </span>
                        </label>
                    @endif

                    <div wire:loading wire:target="photo" class="text-xs font-bold text-gray-500 mt-3 flex justify-center items-center gap-2">
                        <svg class="animate-spin h-3 w-3 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Mengunggah...
                    </div>
                    @error('photo') <span class="text-red-500 text-xs mt-2 block font-medium">{{ $message }}</span> @enderror
                </div>

                <!-- Status -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-7 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Visibilitas Sistem</h2>
                    <select wire:model="is_active"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg text-gray-900 font-bold focus:ring-2 focus:ring-gray-900 outline-none bg-gray-50 mb-3 transition-all duration-300 ease-in-out cursor-pointer">
                        <option value="1">Aktif & Ditampilkan</option>
                        <option value="0">Draf (Disembunyikan)</option>
                    </select>
                    <p class="text-xs text-gray-500 font-medium">Hanya produk "Aktif" yang bisa di-scan/dicari di halaman Kasir.</p>
                </div>
            </div>
        </div>
    </form>
</div>