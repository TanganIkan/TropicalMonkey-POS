<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Stock;
use App\Models\ProductVariant;
use App\Imports\ProductsImport;
use App\Exports\ProductTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';
    public $selectedCategory = '';
    public $selectedBrand = '';
    public $showImportModal = false;
    public $file;

    // Properti Laporan Import
    public $importErrors = [];
    public $importSuccess = null;
    public $importMessage = null;
    public $importMessageType = 'success';
    public $importFailedDetails = [];

    public function downloadTemplate()
    {
        return Excel::download(new ProductTemplateExport(), 'template-import-produk.xlsx');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function openImportModal()
    {
        $this->showImportModal = true;
        $this->reset(['file', 'importErrors', 'importSuccess', 'importMessage', 'importMessageType', 'importFailedDetails']);
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
    }

    public function import()
    {
        $this->validate(['file' => 'required|mimes:xlsx,csv|max:5120']);

        $import = new ProductsImport();
        Excel::import($import, $this->file);

        $failures = $import->failures();

        // 1. Tangkap error validasi bawaan
        if ($failures->count() > 0) {
            $this->importErrors = $failures->map(function ($failure) {
                return [
                    'row' => $failure->row(),
                    'errors' => $failure->errors(),
                ];
            })->toArray();
        } else {
            $this->importErrors = [];
        }

        // 2. Tangkap error pengecekan manual
        $totalFailed = count($import->failedSkus);

        if ($totalFailed > 0 || $failures->count() > 0) {
            $this->importSuccess = null;
            $this->importMessageType = 'warning';
            $this->importMessage = "Proses Selesai! Sukses: {$import->importedCount} data. Ditolak: {$totalFailed} data.";
            $this->importFailedDetails = $import->failedSkus;

            $this->dispatch('swal', [
                'title' => 'Import Selesai dengan Catatan',
                'text' => "Sebagian data gagal diimpor. Silakan cek detailnya di layar.",
                'icon' => 'warning'
            ]);
        } else {
            $this->importMessageType = 'success';
            $this->importSuccess = "Sukses! Seluruh {$import->importedCount} data produk berhasil diimpor ke sistem.";
            $this->importMessage = null;
            $this->importFailedDetails = [];

            $this->closeImportModal();
            $this->dispatch('swal', [
                'title' => 'Import Berhasil!',
                'text' => "Seluruh {$import->importedCount} data produk berhasil dimasukkan ke sistem.",
                'icon' => 'success'
            ]);
        }

        $this->reset('file');
    }

    public function with(): array
    {
        $currentOutletId = session('current_outlet_id');

        $query = Product::with([
            'category',
            'brand',
            'stocks' => function ($query) use ($currentOutletId) {
                $query->where('outlet_id', $currentOutletId);
            },
        ])->where(function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')->orWhere('sku', 'like', '%' . $this->search . '%');
        });

        if ($this->selectedCategory !== '') {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->selectedBrand !== '') {
            $query->where('brand_id', $this->selectedBrand);
        }

        return [
            'products' => $query->latest()->paginate(10),
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }

    public function deleteProduct($id)
    {
        $product = Product::find($id);

        if (!$product) {
            $this->dispatch('swal', [
                'title' => 'Gagal!',
                'text' => 'Produk tidak ditemukan di database.',
                'icon' => 'error'
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($product) {
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }

                Stock::where('product_id', $product->id)->delete();
                ProductVariant::where('product_id', $product->id)->delete();
                $product->delete();
            });

            $this->dispatch('swal', [
                'title' => 'Dihapus!',
                'text' => 'Produk beserta seluruh varian dan stoknya berhasil dihapus.',
                'icon' => 'success'
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                $this->dispatch('swal', [
                    'title' => 'Tidak Bisa Dihapus!',
                    'text' => 'Produk ini gagal dihapus karena sudah memiliki riwayat penjualan/transaksi. Silakan Edit dan ubah statusnya menjadi "Draf (Disembunyikan)".',
                    'icon' => 'error'
                ]);
            } else {
                $this->dispatch('swal', [
                    'title' => 'Terjadi Kesalahan!',
                    'text' => 'Gagal menghapus produk: ' . $e->getMessage(),
                    'icon' => 'error'
                ]);
            }
        }
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 flex-shrink-0 mb-2">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 flex items-center gap-3 tracking-tight">
                Master Data Produk
            </h1>
            <p class="text-sm text-gray-500 mt-1">Kelola semua barang, harga, dan stok di satu tempat.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <button wire:click="openImportModal" type="button"
                class="w-full sm:w-auto bg-white border-2 border-gray-200 text-gray-700 px-5 py-2.5 rounded-xl font-bold hover:bg-gray-50 hover:border-gray-300 transition shadow-sm flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                </svg>
                Import Excel
            </button>
            <a href="/products/create" wire:navigate
                class="w-full sm:w-auto bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-primary/90 transition shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Produk
            </a>
        </div>
    </div>

    <!-- Unified Table Card -->
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col overflow-hidden">
        <!-- Filter & Search Bar -->
        <div class="p-4 lg:p-5 border-b border-gray-100 bg-white flex flex-col sm:flex-row gap-3 md:gap-4">
            <!-- Search Input -->
            <div class="relative w-full sm:flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama produk atau SKU..."
                    class="w-full pl-11 pr-4 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition text-sm">
            </div>

            <!-- Category Filter Dropdown -->
            <div class="relative w-full sm:w-56 flex-shrink-0">
                <select wire:model.live="selectedCategory"
                    class="w-full pl-4 pr-10 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition text-sm appearance-none cursor-pointer font-medium text-gray-700">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </div>

            <!-- Brand Filter Dropdown -->
            <div class="relative w-full sm:w-56 flex-shrink-0">
                <select wire:model.live="selectedBrand"
                    class="w-full pl-4 pr-10 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition text-sm appearance-none cursor-pointer font-medium text-gray-700">
                    <option value="">Semua Brand</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Tabel Responsif -->
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 font-bold">
                        <th class="px-6 py-4">Informasi Produk</th>
                        <th class="px-6 py-4">Brand & Kategori</th>
                        <th class="px-6 py-4 text-right">Harga Jual</th>
                        <th class="px-6 py-4 text-center">Stok Aktif</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($products as $product)
                        <tr class="hover:bg-gray-50/80 transition">
                            <!-- 1. INFORMASI PRODUK -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-4">
                                    @if ($product->image)
                                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}"
                                            class="w-12 h-12 rounded-xl object-cover border border-gray-200 flex-shrink-0 shadow-sm">
                                    @else
                                        <div
                                            class="w-12 h-12 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">{{ $product->name }}</p>
                                        <p class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-1.5">
                                            <span
                                                class="font-mono bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded text-gray-600 font-medium">
                                                SKU: {{ $product->sku ?? 'Ber-varian' }}
                                            </span>

                                            <!-- Menambahkan Info Barcode -->
                                            @if($product->barcode)
                                                <span
                                                    class="font-mono bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded text-gray-600 font-medium">
                                                    BC: {{ $product->barcode }}
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- 2. BRAND & KATEGORI (Digabung) -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col items-start gap-1.5">
                                    @if($product->brand)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-gray-900 text-white uppercase tracking-wider shadow-sm">
                                            {{ $product->brand->name }}
                                        </span>
                                    @endif
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-white text-gray-600 border border-gray-200 uppercase tracking-wider">
                                        {{ $product->category->name ?? 'Tanpa Kategori' }}
                                    </span>
                                </div>
                            </td>

                            <!-- 3. HARGA JUAL -->
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-black text-right tracking-tight">
                                Rp {{ number_format($product->sell_price, 0, ',', '.') }}
                            </td>

                            <!-- 4. STOK AKTIF -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php
                                    $stockQty = $product->stocks->sum('quantity') ?? 0;
                                @endphp
                                @if ($stockQty > 10)
                                    <span
                                        class="inline-flex items-center justify-center min-w-[2.5rem] px-2.5 py-1 rounded-lg text-sm font-bold bg-white border-2 border-gray-900 text-gray-900">
                                        {{ $stockQty }}
                                    </span>
                                @elseif($stockQty > 0)
                                    <!-- Jika menipis, beri sedikit aksen abu-abu gelap -->
                                    <span
                                        class="inline-flex items-center justify-center min-w-[2.5rem] px-2.5 py-1 rounded-lg text-sm font-bold bg-gray-100 border-2 border-gray-400 text-gray-700">
                                        {{ $stockQty }}
                                    </span>
                                @else
                                    <!-- Jika habis -->
                                    <span
                                        class="inline-flex items-center justify-center min-w-[2.5rem] px-2.5 py-1 rounded-lg text-sm font-bold bg-gray-50 border-2 border-gray-200 text-gray-400">
                                        0
                                    </span>
                                @endif
                            </td>

                            <!-- 5. STATUS -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($product->is_active)
                                    <div class="flex items-center justify-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-gray-900"></div>
                                        <span class="text-xs font-bold text-gray-900">Aktif</span>
                                    </div>
                                @else
                                    <div class="flex items-center justify-center gap-1.5 opacity-50">
                                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                        <span class="text-xs font-bold text-gray-500">Nonaktif</span>
                                    </div>
                                @endif
                            </td>

                            <!-- 6. AKSI -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    <a href="/products/{{ $product->id }}/add-stock" wire:navigate
                                        class="p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition border border-transparent hover:border-gray-200"
                                        title="Tambah Stok">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 14v7m-3-3h6"></path>
                                        </svg>
                                    </a>
                                    <a href="/products/{{ $product->id }}/edit" wire:navigate
                                        class="p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition border border-transparent hover:border-gray-200"
                                        title="Edit Produk">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </a>
                                    <button type="button" x-data
                                        @click="confirmDeletion(() => $wire.deleteProduct({{ $product->id }}), 'Produk {{ $product->name }}')"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition border border-transparent hover:border-red-100"
                                        title="Hapus Produk">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    </div>
                                    <p class="text-base font-bold text-gray-500">Belum ada data produk</p>
                                    <p class="text-sm mt-1">Silakan tambah produk baru atau import dari Excel.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">{{ $products->links() }}</div>
        @endif
    </div>

    @if ($showImportModal)
        <template x-teleport="body">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-[60] p-4 transition-opacity"
                wire:click.self="closeImportModal">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" wire:click.stop>
                    <!-- Tambahan wire:click.stop agar klik di dalam kotak putih tidak menutup modal -->
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/80">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Import Data Produk
                        </h2>
                        <button wire:click="closeImportModal"
                            class="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-200 rounded-full transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12">
                                </path>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 max-h-[80vh] overflow-y-auto">
                        <button type="button" wire:click="downloadTemplate"
                            class="w-full text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 border-2 border-gray-200 rounded-xl px-4 py-3.5 mb-6 transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Download Template Excel
                        </button>

                        <form wire:submit="import">
                            <div class="mb-6">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Upload File (.xlsx, .csv)</label>
                                <input type="file" wire:model="file" accept=".xlsx,.csv"
                                    class="w-full text-sm border-2 border-gray-200 border-dashed rounded-xl px-3 py-4 bg-gray-50/50 focus:outline-none focus:border-gray-900 transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-black cursor-pointer">
                                @error('file') <span
                                    class="text-red-500 text-xs mt-2 block font-medium">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Notifikasi Sistem -->
                            @if ($importSuccess)
                                <div
                                    class="bg-green-50 border border-green-200 text-green-700 text-sm p-4 rounded-xl mb-4 font-bold flex items-start gap-2">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $importSuccess }}
                                </div>
                            @endif

                            @if ($importMessage)
                                <div
                                    class="{{ $importMessageType === 'warning' ? 'bg-orange-50 border-orange-200 text-orange-800' : 'bg-green-50 border-green-200 text-green-700' }} border text-sm p-4 rounded-xl mb-4">
                                    <p class="font-bold mb-2 flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                            </path>
                                        </svg>
                                        {{ $importMessage }}
                                    </p>
                                    @if(count($importFailedDetails) > 0)
                                        <ul class="space-y-1 max-h-32 overflow-y-auto mt-2">
                                            @foreach($importFailedDetails as $failedSku)
                                                <li class="bg-white p-2 rounded-lg text-xs font-mono border border-orange-100">
                                                    {{ $failedSku }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            @if (count($importErrors) > 0)
                                <div
                                    class="bg-red-50 border border-red-200 text-red-700 text-sm p-4 rounded-xl mb-4 max-h-40 overflow-y-auto">
                                    <p class="font-bold mb-2 flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Peringatan Format Excel:
                                    </p>
                                    <ul class="space-y-1 mt-2">
                                        @foreach ($importErrors as $error)
                                            <li class="bg-white p-2 rounded-lg text-xs border border-red-100">
                                                <span class="font-bold">Baris {{ $error['row'] }}:</span>
                                                {{ implode(', ', $error['errors']) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="flex gap-3 justify-end mt-8">
                                <button type="button" wire:click="closeImportModal"
                                    class="px-5 py-2.5 text-gray-700 font-bold hover:bg-gray-100 rounded-xl text-sm transition bg-white border border-gray-200 shadow-sm w-full">Batal</button>
                                <button type="submit"
                                    class="px-5 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-bold hover:bg-black transition shadow-lg shadow-gray-900/20 flex items-center justify-center gap-2 w-full">
                                    <span wire:loading.remove wire:target="import">Import Data</span>
                                    <span wire:loading wire:target="import">Memproses...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>