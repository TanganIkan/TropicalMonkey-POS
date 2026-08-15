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

        $this->dispatch('swal', [
            'title' => 'Berhasil!',
            'text' => 'Kategori produk baru berhasil ditambahkan.',
            'icon' => 'success'
        ]);
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            $this->dispatch('swal', [
                'title' => 'Akses Ditolak!',
                'text' => 'Kategori ini tidak bisa dihapus karena masih menampung produk.',
                'icon' => 'error'
            ]);
            return;
        }
        $category->delete();

        $this->dispatch('swal', [
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

        $this->dispatch('swal', [
            'title' => 'Berhasil!',
            'text' => 'Brand baru berhasil ditambahkan.',
            'icon' => 'success'
        ]);
    }

    public function deleteBrand($id)
    {
        $brand = Brand::findOrFail($id);
        if ($brand->products()->count() > 0) {
            $this->dispatch('swal', [
                'title' => 'Akses Ditolak!',
                'text' => 'Brand ini tidak bisa dihapus karena masih digunakan oleh produk.',
                'icon' => 'error'
            ]);
            return;
        }
        $brand->delete();

        $this->dispatch('swal', [
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

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen">

    <!-- Header -->
    <div class="mb-2 md:mb-4">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
            Manajemen Kategori & Brand
        </h1>
        <p class="text-sm text-gray-500 mt-2">Kelola dan atur penamaan kelompok produk Anda.</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-gray-200 mb-6 overflow-x-auto">
        <nav class="-mb-px flex space-x-8 min-w-max">
            <button wire:click="$set('activeTab', 'category')"
                class="whitespace-nowrap py-4 px-2 border-b-2 font-bold text-sm transition-all duration-300 ease-in-out {{ $activeTab === 'category' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300' }}">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                        </path>
                    </svg>
                    Kategori Produk
                </span>
            </button>
            <button wire:click="$set('activeTab', 'brand')"
                class="whitespace-nowrap py-4 px-2 border-b-2 font-bold text-sm transition-all duration-300 ease-in-out {{ $activeTab === 'brand' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300' }}">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9">
                        </path>
                    </svg>
                    Brand / Merek
                </span>
            </button>
        </nav>
    </div>

    <!-- TAB KATEGORI -->
    @if ($activeTab === 'category')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8" x-data
            x-transition:enter="transition-all ease-in-out duration-300" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">

            <!-- Form Tambah -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 md:p-7 rounded-2xl border border-gray-200 shadow-sm sticky top-6">
                    <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Kategori Baru
                    </h3>
                    <form wire:submit="saveCategory" class="space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Kategori</label>
                            <input type="text" wire:model="categoryName" placeholder="Misal: T-Shirt, Celana..."
                                class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300 ease-in-out">
                            @error('categoryName')
                                <span class="text-xs text-red-500 mt-1.5 font-medium block">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit"
                            class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3 px-4 rounded-lg text-sm transition-all duration-300 shadow-lg shadow-gray-900/20 flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="saveCategory">Simpan Kategori</span>
                            <span wire:loading wire:target="saveCategory">Memproses...</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap min-w-[450px]">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4 font-bold text-gray-500 uppercase tracking-wider text-xs">Nama Kategori
                                </th>
                                <th class="px-6 py-4 font-bold text-gray-500 uppercase tracking-wider text-xs text-center">
                                    Statistik</th>
                                <th class="px-6 py-4 font-bold text-gray-500 uppercase tracking-wider text-xs text-right">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($categories as $category)
                                <tr class="hover:bg-gray-50/80 transition-all duration-300 ease-in-out">
                                    <td class="px-6 py-4 text-gray-900 font-bold text-base">
                                        {{ $category->name }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex flex-col items-center justify-center gap-1.5">
                                            <span
                                                class="bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                {{ $category->products_count }} Produk
                                            </span>
                                            @if ($category->variants_count > 0)
                                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                                    {{ $category->variants_count }} Varian
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button type="button" x-data
                                            @click="confirmDeletion(() => $wire.deleteCategory({{ $category->id }}), 'Kategori {{ $category->name }}')"
                                            class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 border border-transparent hover:border-red-100 transition-all duration-300"
                                            title="Hapus Kategori">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <div
                                                class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 border border-gray-100">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z">
                                                    </path>
                                                </svg>
                                            </div>
                                            <p class="font-bold text-gray-500">Belum ada kategori terdaftar.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB BRAND -->
    @if ($activeTab === 'brand')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8" x-data
            x-transition:enter="transition-all ease-in-out duration-300" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">

            <!-- Form Tambah -->
            <div class="lg:col-span-1">
                <div class="bg-white p-5 md:p-7 rounded-2xl border border-gray-200 shadow-sm sticky top-6">
                    <h3 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Tambah Brand Baru
                    </h3>
                    <form wire:submit="saveBrand" class="space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Nama Brand</label>
                            <input type="text" wire:model="brandName" placeholder="Contoh: Nike, Adidas..."
                                class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300 ease-in-out">
                            @error('brandName')
                                <span class="text-xs text-red-500 mt-1.5 font-medium block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">Deskripsi <span
                                    class="text-xs font-normal text-gray-400">(Opsional)</span></label>
                            <textarea wire:model="brandDescription" rows="3" placeholder="Catatan opsional tentang brand..."
                                class="w-full px-4 py-2.5 border border-gray-200 bg-gray-50 focus:bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all duration-300 ease-in-out"></textarea>
                        </div>
                        <button type="submit"
                            class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3 px-4 rounded-lg text-sm transition-all duration-300 shadow-lg shadow-gray-900/20 flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="saveBrand">Simpan Brand</span>
                            <span wire:loading wire:target="saveBrand">Memproses...</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabel Data -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap min-w-[450px]">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-4 font-bold text-gray-500 uppercase tracking-wider text-xs">Info Brand
                                </th>
                                <th class="px-6 py-4 font-bold text-gray-500 uppercase tracking-wider text-xs text-center">
                                    Statistik</th>
                                <th class="px-6 py-4 font-bold text-gray-500 uppercase tracking-wider text-xs text-right">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($brands as $brand)
                                <tr class="hover:bg-gray-50/80 transition-all duration-300 ease-in-out">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div
                                                class="w-10 h-10 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center font-bold flex-shrink-0 border border-gray-200">
                                                {{ strtoupper(substr($brand->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900 text-base">{{ $brand->name }}</p>
                                                @if ($brand->description)
                                                    <p
                                                        class="text-xs text-gray-400 font-medium mt-0.5 truncate max-w-[200px] md:max-w-[250px]">
                                                        {{ $brand->description }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="bg-gray-100 text-gray-700 border border-gray-200 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                            {{ $brand->products_count }} Produk
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button type="button" x-data
                                            @click="confirmDeletion(() => $wire.deleteBrand({{ $brand->id }}), 'Brand {{ $brand->name }}')"
                                            class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 border border-transparent hover:border-red-100 transition-all duration-300"
                                            title="Hapus Brand">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <div
                                                class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 border border-gray-100">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9">
                                                    </path>
                                                </svg>
                                            </div>
                                            <p class="font-bold text-gray-500">Belum ada brand terdaftar.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>