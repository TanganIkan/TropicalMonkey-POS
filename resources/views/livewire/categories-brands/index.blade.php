<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Category;
use App\Models\Brand;

new #[Layout('components.layouts.app')] class extends Component {
    public $activeTab = 'category';

    public $categoryName = '';

    public $brandName = '';
    public $brandDescription = '';

    // --- LOGIKA KATEGORI ---
    public function saveCategory()
    {
        $this->validate(
            [
                'categoryName' => 'required|min:2|unique:categories,name',
            ],
            [
                'categoryName.required' => 'Nama kategori wajib diisi.',
                'categoryName.unique' => 'Kategori ini sudah ada.',
            ],
        );

        Category::create(['name' => trim($this->categoryName)]);
        $this->reset('categoryName');

        $this->dispatch('notify', [
            'title' => 'Berhasil!',
            'text' => 'Kategori produk baru berhasil ditambahkan.',
            'icon' => 'success'
        ]);
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            $this->dispatch('notify', [
                'title' => 'Akses Ditolak!',
                'text' => 'Kategori ini tidak bisa dihapus karena masih menampung produk.',
                'icon' => 'error'
            ]);
            return;
        }
        $category->delete();

        $this->dispatch('notify', [
            'title' => 'Dihapus!',
            'text' => 'Kategori berhasil dihapus dari sistem.',
            'icon' => 'success'
        ]);
    }

    // --- LOGIKA BRAND ---
    public function saveBrand()
    {
        $this->validate(
            [
                'brandName' => 'required|min:2|unique:brands,name',
                'brandDescription' => 'nullable|string',
            ],
            [
                'brandName.required' => 'Nama brand wajib diisi.',
                'brandName.unique' => 'Brand ini sudah ada.',
            ],
        );

        Brand::create([
            'name' => trim($this->brandName),
            'description' => trim($this->brandDescription),
        ]);

        $this->reset(['brandName', 'brandDescription']);

        $this->dispatch('notify', [
            'title' => 'Berhasil!',
            'text' => 'Brand baru berhasil ditambahkan.',
            'icon' => 'success'
        ]);
    }

    public function deleteBrand($id)
    {
        $brand = Brand::findOrFail($id);
        if ($brand->products()->count() > 0) {
            $this->dispatch('notify', [
                'title' => 'Akses Ditolak!',
                'text' => 'Brand ini tidak bisa dihapus karena masih digunakan oleh produk.',
                'icon' => 'error'
            ]);
            return;
        }
        $brand->delete();

        $this->dispatch('notify', [
            'title' => 'Dihapus!',
            'text' => 'Brand berhasil dihapus dari sistem.',
            'icon' => 'success'
        ]);
    }

    public function with(): array
    {
        return [
            'categories' => Category::withCount(['products', 'variants'])
                ->orderBy('name')
                ->get(),

            'brands' => Brand::withCount(['products', 'variants'])
                ->orderBy('name')
                ->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Kategori & Brand" subtitle="Kelompokkan produk berdasarkan kategori dan brand.">
        <div class="flex gap-2 rounded-2xl bg-white border border-zinc-200 p-1">
            <button wire:click="$set('activeTab', 'category')" class="{{ $activeTab === 'category' ? 'chip-active' : 'chip !border-transparent' }} !py-1.5">Kategori <span class="opacity-60">{{ $categories->count() }}</span></button>
            <button wire:click="$set('activeTab', 'brand')" class="{{ $activeTab === 'brand' ? 'chip-active' : 'chip !border-transparent' }} !py-1.5">Brand <span class="opacity-60">{{ $brands->count() }}</span></button>
        </div>
    </x-ui.page-header>

    @if ($activeTab === 'category')
        <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
            <div class="tile lg:sticky lg:top-4">
                <h3 class="tile-title mb-4">Tambah Kategori</h3>
                <form wire:submit="saveCategory" class="space-y-4">
                    <div>
                        <label class="label">Nama kategori</label>
                        <input type="text" wire:model="categoryName" placeholder="mis. T-Shirt, Celana" class="input">
                        @error('categoryName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="btn-primary w-full">
                        <span wire:loading.remove wire:target="saveCategory">Simpan Kategori</span>
                        <span wire:loading wire:target="saveCategory">Menyimpan...</span>
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse ($categories as $category)
                    <div class="tile !p-4 flex items-center gap-3" wire:key="category-{{ $category->id }}">
                        <div class="w-11 h-11 rounded-2xl bg-ink text-white flex items-center justify-center text-sm font-bold flex-shrink-0">{{ strtoupper(mb_substr($category->name, 0, 1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $category->name }}</p>
                            <p class="text-xs text-zinc-500 mt-0.5">{{ $category->products_count }} produk @if ($category->variants_count > 0) · {{ $category->variants_count }} varian @endif</p>
                        </div>
                        <button type="button" x-data @click="confirmDeletion(() => $wire.deleteCategory({{ $category->id }}), @js('Kategori ' . $category->name))"
                            class="btn-icon hover:!text-red-600 hover:!bg-red-50" title="Hapus"><x-icon name="trash" class="w-4 h-4" /></button>
                    </div>
                @empty
                    <div class="tile sm:col-span-2"><x-ui.empty icon="tag" title="Belum ada kategori" /></div>
                @endforelse
            </div>
        </div>
    @endif

    @if ($activeTab === 'brand')
        <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
            <div class="tile lg:sticky lg:top-4">
                <h3 class="tile-title mb-4">Tambah Brand</h3>
                <form wire:submit="saveBrand" class="space-y-4">
                    <div>
                        <label class="label">Nama brand</label>
                        <input type="text" wire:model="brandName" placeholder="mis. Nike, Adidas" class="input">
                        @error('brandName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="label">Deskripsi <span class="font-normal text-zinc-400">(opsional)</span></label>
                        <textarea wire:model="brandDescription" rows="3" placeholder="Catatan tentang brand..." class="input"></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full">
                        <span wire:loading.remove wire:target="saveBrand">Simpan Brand</span>
                        <span wire:loading wire:target="saveBrand">Menyimpan...</span>
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse ($brands as $brand)
                    <div class="tile !p-4 flex items-center gap-3" wire:key="brand-{{ $brand->id }}">
                        <div class="w-11 h-11 rounded-2xl bg-ink text-white flex items-center justify-center text-sm font-bold flex-shrink-0">{{ strtoupper(mb_substr($brand->name, 0, 1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $brand->name }}</p>
                                @if ($brand->description)
                                    <p class="text-xs text-zinc-400 truncate">{{ $brand->description }}</p>
                                @endif
                            <p class="text-xs text-zinc-500 mt-0.5">{{ $brand->products_count }} produk @if ($brand->variants_count > 0) · {{ $brand->variants_count }} varian @endif</p>
                        </div>
                        <button type="button" x-data @click="confirmDeletion(() => $wire.deleteBrand({{ $brand->id }}), @js('Brand ' . $brand->name))"
                            class="btn-icon hover:!text-red-600 hover:!bg-red-50" title="Hapus"><x-icon name="trash" class="w-4 h-4" /></button>
                    </div>
                @empty
                    <div class="tile sm:col-span-2"><x-ui.empty icon="star" title="Belum ada brand" /></div>
                @endforelse
            </div>
        </div>
    @endif
</div>
