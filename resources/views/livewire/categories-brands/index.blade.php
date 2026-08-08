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
        session()->flash('success', 'Kategori berhasil ditambahkan!');
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            session()->flash('error', 'Kategori tidak bisa dihapus karena sedang digunakan oleh produk.');
            return;
        }
        $category->delete();
        session()->flash('success', 'Kategori berhasil dihapus!');
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
        session()->flash('success', 'Brand berhasil ditambahkan!');
    }

    public function deleteBrand($id)
    {
        $brand = Brand::findOrFail($id);
        if ($brand->products()->count() > 0) {
            session()->flash('error', 'Brand tidak bisa dihapus karena sedang digunakan oleh produk.');
            return;
        }
        $brand->delete();
        session()->flash('success', 'Brand berhasil dihapus!');
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

<div class="p-4 md:p-6 flex flex-col space-y-4 md:space-y-6">
    <div class="mb-6">
        <h2 class="text-xl md:text-2xl font-bold text-gray-900">Manajemen Kategori & Brand</h2>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Kelola daftar kategori dan brand untuk produk Anda.</p>
    </div>

    <!-- Alert Messages -->
    @if (session()->has('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm flex items-center">
            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm flex items-center">
            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                </path>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabs Navigation (Ditambahkan overflow-x-auto agar bisa di-scroll di HP) -->
    <div class="border-b border-gray-200 mb-6 overflow-x-auto">
        <nav class="-mb-px flex space-x-6 md:space-x-8 min-w-max">
            <button wire:click="$set('activeTab', 'category')"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition {{ $activeTab === 'category' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Kategori Produk
            </button>
            <button wire:click="$set('activeTab', 'brand')"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition {{ $activeTab === 'brand' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Brand Produk
            </button>
        </nav>
    </div>

    <!-- TAB KATEGORI -->
    @if ($activeTab === 'category')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Tambah -->
            <div class="lg:col-span-1">
                <div class="bg-white p-4 md:p-5 rounded-xl border border-gray-200 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Tambah Kategori Baru</h3>
                    <form wire:submit="saveCategory" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nama Kategori</label>
                            <input type="text" wire:model="categoryName"
                                placeholder="Contoh: Tshirt ladies crop (TL)"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                            @error('categoryName')
                                <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit"
                            class="w-full bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm">
                            Simpan Kategori
                        </button>
                    </form>
                </div>
            </div>
            <!-- Tabel Data (Ditambahkan overflow-x-auto untuk layar HP) -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap min-w-[450px]">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-gray-600">Nama Kategori</th>
                                <th class="px-4 py-3 font-semibold text-gray-600 text-center">Jumlah Produk</th>
                                <th class="px-4 py-3 font-semibold text-gray-600 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($categories as $category)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 py-3 text-gray-800 font-medium">{{ $category->name }}</td>
                                    <td class="px-4 py-3 text-center text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <span
                                                class="bg-blue-100 text-blue-700 px-2 py-1 rounded-md text-xs font-bold mb-1">
                                                {{ $category->products_count }} Model (Induk)
                                            </span>
                                            @if ($category->variants_count > 0)
                                                <span class="text-[11px] text-gray-500 font-medium">
                                                    Total {{ $category->variants_count }} Varian
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button wire:click="deleteCategory({{ $category->id }})"
                                            wire:confirm="Yakin ingin menghapus kategori ini?"
                                            class="text-red-500 hover:text-red-700 p-1.5 rounded-md hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400">Belum ada kategori
                                        terdaftar.
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Tambah -->
            <div class="lg:col-span-1">
                <div class="bg-white p-4 md:p-5 rounded-xl border border-gray-200 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Tambah Brand Baru</h3>
                    <form wire:submit="saveBrand" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nama Brand</label>
                            <input type="text" wire:model="brandName" placeholder="Contoh: Nike, Adidas..."
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                            @error('brandName')
                                <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Deskripsi (Opsional)</label>
                            <textarea wire:model="brandDescription" rows="2" placeholder="Catatan opsional..."
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition"></textarea>
                        </div>
                        <button type="submit"
                            class="w-full bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition shadow-sm">
                            Simpan Brand
                        </button>
                    </form>
                </div>
            </div>
            <!-- Tabel Data (Ditambahkan overflow-x-auto untuk layar HP) -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap min-w-[450px]">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-gray-600">Info Brand</th>
                                <th class="px-4 py-3 font-semibold text-gray-600 text-center">Jumlah Produk</th>
                                <th class="px-4 py-3 font-semibold text-gray-600 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($brands as $brand)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 py-3 text-gray-800 font-medium">
                                        {{ $brand->name }}
                                        @if ($brand->description)
                                            <p
                                                class="text-xs text-gray-400 font-normal mt-0.5 truncate max-w-[150px] md:max-w-[200px]">
                                                {{ $brand->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-500">
                                        <span
                                            class="bg-gray-100 px-2 py-1 rounded-md text-xs font-semibold">{{ $brand->products_count }}
                                            item</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button wire:click="deleteBrand({{ $brand->id }})"
                                            wire:confirm="Yakin ingin menghapus brand ini?"
                                            class="text-red-500 hover:text-red-700 p-1.5 rounded-md hover:bg-red-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400">Belum ada brand
                                        terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
