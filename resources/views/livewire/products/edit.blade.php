<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Services\ProductService;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public Product $product;

    #[Rule('required|min:3', message: ['name.required' => 'Nama produk wajib diisi.', 'name.min' => 'Nama produk minimal 3 karakter.'])]
    public $name = '';

    #[Rule('required', message: 'Kategori wajib dipilih.')]
    public $category_id = '';

    #[Rule('nullable|exists:brands,id', message: 'Brand tidak valid.')]
    public $brand_id = '';

    public $cost_price = null;

    #[Rule('required|numeric|min:0', message: ['sell_price.required' => 'Harga jual wajib diisi.', 'sell_price.numeric' => 'Harga jual harus berupa angka.', 'sell_price.min' => 'Harga jual tidak boleh minus.'])]
    public $sell_price = null;

    public $sku = '';
    public $barcode = '';

    public $is_active = 1;

    #[Rule('nullable|image|max:5120', message: ['photo.image' => 'Foto harus berupa gambar (JPG/PNG).', 'photo.max' => 'Ukuran foto maksimal 5MB.'])]
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

    public function save(ProductService $products)
    {
        $this->validate();

        try {
            $products->update($this->product, [
                'name' => $this->name,
                'category_id' => $this->category_id,
                'brand_id' => $this->brand_id,
                'cost_price' => $this->cost_price,
                'sell_price' => $this->sell_price,
                'is_active' => $this->is_active,
                'sku' => $this->sku,
                'barcode' => $this->barcode,
            ], $this->photo, $this->removeCurrentPhoto);
        } catch (\App\Exceptions\PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        session()->flash('notify', ['title' => 'Produk Diperbarui', 'text' => 'Perubahan data produk berhasil disimpan.', 'icon' => 'success']);
        $this->redirect('/products', navigate: true);
    }
};
?>

<form wire:submit="save" class="space-y-4">
    <x-ui.page-header title="Edit Produk" :subtitle="$product->name" back="/products" back-label="Data produk">
        <a href="/products/{{ $product->id }}/add-stock" wire:navigate class="btn-secondary"><x-icon name="layers" class="w-4 h-4" /> Stok & Varian</a>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
            <span wire:loading wire:target="save">Menyimpan...</span>
        </button>
    </x-ui.page-header>

    <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
        <div class="lg:col-span-2 space-y-3 md:space-y-4">
            <div class="tile">
                <h2 class="tile-title mb-4">Informasi Dasar</h2>
                <div class="space-y-4">
                    <div>
                        <label class="label">Nama produk <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" class="input">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Kategori <span class="text-red-500">*</span></label>
                            <select wire:model="category_id" class="select">
                                <option value="">Pilih kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Brand</label>
                            <select wire:model="brand_id" class="select">
                                <option value="">Pilih brand</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            @error('brand_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="tile">
                <h2 class="tile-title mb-4">Harga & Kode</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Harga modal</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rp</span>
                            <input type="number" wire:model="cost_price" class="input !pl-11">
                        </div>
                    </div>
                    <div>
                        <label class="label">Harga jual <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rp</span>
                            <input type="number" wire:model="sell_price" class="input !pl-11 font-semibold">
                        </div>
                        @error('sell_price') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if (!$product->has_variants)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="label">Kode SKU</label>
                            <input type="text" wire:model="sku" class="input font-mono">
                        </div>
                        <div>
                            <label class="label">Barcode</label>
                            <input type="text" wire:model="barcode" class="input font-mono">
                        </div>
                    </div>
                @else
                    <div class="mt-4 rounded-2xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600 flex gap-3">
                        <x-icon name="info" class="w-4 h-4 mt-0.5 text-zinc-400" />
                        <span><b>Produk bervarian.</b> SKU, barcode, dan harga per varian dikelola di menu Kelola Stok & Varian.</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-3 md:space-y-4 lg:sticky lg:top-4">
            <div class="tile">
                <h2 class="tile-title mb-4">Foto Produk</h2>
                @if ($photo)
                    <div class="relative rounded-2xl overflow-hidden">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-56 object-cover">
                        <button type="button" wire:click="removePhoto" class="absolute top-3 right-3 w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center hover:text-red-600" aria-label="Batalkan foto baru"><x-icon name="x" class="w-4 h-4" /></button>
                    </div>
                @elseif ($currentImage)
                    <div class="relative rounded-2xl overflow-hidden">
                        <img src="{{ Storage::url($currentImage) }}" class="w-full h-56 object-cover">
                        <button type="button" wire:click="removeSavedPhoto" class="absolute top-3 right-3 w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center hover:text-red-600" aria-label="Hapus foto"><x-icon name="trash" class="w-4 h-4" /></button>
                    </div>
                    <label class="btn-secondary w-full mt-3 cursor-pointer">
                        <x-icon name="image" class="w-4 h-4" /> Ganti foto
                        <input type="file" wire:model="photo" accept="image/*" class="hidden">
                    </label>
                @else
                    <label class="block rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 p-8 text-center cursor-pointer hover:border-zinc-400 transition">
                        <div class="w-12 h-12 rounded-2xl bg-white mx-auto mb-3 flex items-center justify-center"><x-icon name="image" class="text-zinc-400" /></div>
                        <p class="text-sm font-semibold">Pilih gambar</p>
                        <p class="text-xs text-zinc-400 mt-1">PNG / JPG, maks. 5MB</p>
                        <input type="file" wire:model="photo" accept="image/*" class="hidden">
                    </label>
                @endif
                <p wire:loading wire:target="photo" class="text-xs text-zinc-500 mt-3">Mengunggah...</p>
                @error('photo') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="tile">
                <h2 class="tile-title mb-3">Status</h2>
                <select wire:model="is_active" class="select">
                    <option value="1">Aktif & ditampilkan</option>
                    <option value="0">Draf (disembunyikan)</option>
                </select>
                <p class="text-xs text-zinc-500 mt-2">Hanya produk aktif yang bisa di-scan di kasir.</p>
            </div>
        </div>
    </div>
</form>
