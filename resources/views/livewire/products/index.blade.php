<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Imports\ProductsImport;
use App\Exports\ProductTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';
    public $showImportModal = false;
    public $file;
    public $importErrors = [];
    public $importSuccess = null;

    public function downloadTemplate()
    {
        return Excel::download(new ProductTemplateExport(), 'template-import-produk.xlsx');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openImportModal()
    {
        $this->showImportModal = true;
        $this->reset(['file', 'importErrors', 'importSuccess']);
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
    }

    public function import()
    {
        $this->validate([
            'file' => 'required|mimes:xlsx,csv|max:5120',
        ]);

        $import = new ProductsImport();
        Excel::import($import, $this->file);

        $failures = $import->failures();

        if ($failures->count() > 0) {
            $this->importErrors = $failures->map(function ($failure) {
                return [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                ];
            })->toArray();
        } else {
            $this->importSuccess = 'Import berhasil! Data produk dan stok telah diperbarui.';
            $this->reset('file');
        }
    }

    public function with(): array
    {
        $currentOutletId = session('current_outlet_id');

        return [
            // PERBARUAN: Memuat kategori DAN stok yang HANYA milik outlet yang sedang login
            'products' => Product::with(['category', 'stocks' => function($query) use ($currentOutletId) {
                    $query->where('outlet_id', $currentOutletId);
                }])
                ->where(function($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('sku', 'like', '%' . $this->search . '%');
                })
                ->latest()
                ->paginate(10)
        ];
    }
};
?>

<div class="p-4 md:p-6 flex flex-col space-y-4 md:space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 flex-shrink-0">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                Master Data Produk
            </h1>
            <p class="text-sm text-gray-500 mt-1">Kelola semua barang, harga, dan stok di satu tempat.</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
            <button wire:click="openImportModal" type="button"
                class="bg-white border border-gray-200 text-gray-700 px-4 py-2.5 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-300 transition shadow-sm flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>
                Import Excel
            </button>
            <a href="/products/create" wire:navigate
                class="bg-primary text-white px-4 py-2.5 rounded-xl font-semibold hover:bg-primary/90 transition shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Tambah Produk
            </a>
        </div>
    </div>

    <!-- Unified Table Card -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
        
        <!-- Search Bar dalam Card -->
        <div class="p-4 border-b border-gray-100 bg-gray-50/50">
            <div class="relative w-full sm:max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 md:pl-4 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama produk atau SKU..."
                    class="w-full pl-10 md:pl-12 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition text-sm shadow-sm">
            </div>
        </div>

        <!-- Tabel Responsif -->
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-gray-50/80 backdrop-blur sticky top-0 z-10 border-b border-gray-200">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 font-semibold">
                        <th class="px-6 py-4">Informasi Produk</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4 text-right">Harga Modal</th>
                        <th class="px-6 py-4 text-right">Harga Jual</th>
                        <!-- PERBARUAN: Tambah Kolom Stok -->
                        <th class="px-6 py-4 text-center">Stok Aktif</th> 
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($products as $product)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">{{ $product->name }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">SKU: <span class="font-mono bg-gray-100 px-1 py-0.5 rounded">{{ $product->sku }}</span></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ $product->category->name ?? 'Tanpa Kategori' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                Rp {{ number_format($product->cost_price, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold text-right">
                                Rp {{ number_format($product->sell_price, 0, ',', '.') }}
                            </td>
                            
                            <!-- PERBARUAN: Data Stok Dinamis -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php
                                    // Ambil kuantitas dari relasi stok (jika kosong, set jadi 0)
                                    $stockQty = $product->stocks->first()->quantity ?? 0;
                                @endphp
                                
                                @if($stockQty > 10)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-bold bg-green-50 text-green-700 border border-green-200">
                                        {{ $stockQty }}
                                    </span>
                                @elseif($stockQty > 0)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-bold bg-yellow-50 text-yellow-700 border border-yellow-200" title="Stok Menipis">
                                        {{ $stockQty }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-bold bg-red-50 text-red-700 border border-red-200" title="Stok Kosong">
                                        0
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($product->is_active)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700">Aktif</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-400">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    <button class="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition" title="Edit Produk">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Hapus Produk">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="w-16 h-16 mb-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    <p class="text-base font-medium text-gray-500">Belum ada data produk</p>
                                    <p class="text-sm mt-1">Klik "Tambah Produk" atau "Import Excel" untuk memulai.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($products->hasPages())
            <div class="px-4 py-4 border-t border-gray-100 bg-white">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <!-- Import Modal -->
    @if($showImportModal)
    <!-- ... (Bagian modal tetap sama persis seperti sebelumnya) ... -->
        <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4 transition-opacity" wire:click.self="closeImportModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Import Data Produk
                    </h2>
                    <button wire:click="closeImportModal" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-5">
                    <button type="button" wire:click="downloadTemplate" class="w-full text-sm font-medium text-primary bg-primary/5 hover:bg-primary/10 border border-primary/20 rounded-xl px-4 py-3 mb-5 transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download Template Excel
                    </button>

                    <form wire:submit="import">
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upload File Import (.xlsx, .csv)</label>
                            <input type="file" wire:model="file" accept=".xlsx,.csv"
                                class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                            @error('file') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            <div wire:loading wire:target="file" class="text-xs text-primary font-medium mt-2 flex items-center gap-1">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Mengunggah file...
                            </div>
                        </div>

                        @if($importSuccess)
                            <div class="bg-green-50 border border-green-200 text-green-700 text-sm font-medium p-3 rounded-xl mb-4 flex items-start gap-2">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                {{ $importSuccess }}
                            </div>
                        @endif

                        @if(count($importErrors) > 0)
                            <div class="bg-red-50 border border-red-200 text-red-700 text-sm p-4 rounded-xl mb-4 max-h-40 overflow-y-auto">
                                <p class="font-bold mb-2 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Peringatan Import:
                                </p>
                                <ul class="space-y-1">
                                    @foreach($importErrors as $error)
                                        <li class="bg-white/60 p-2 rounded-lg text-xs">
                                            <span class="font-bold">Baris {{ $error['row'] }}:</span> {{ implode(', ', $error['errors']) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="flex gap-3 justify-end pt-2 border-t border-gray-100">
                            <button type="button" wire:click="closeImportModal"
                                class="px-5 py-2.5 text-gray-600 font-semibold hover:bg-gray-100 rounded-xl text-sm transition">Batal</button>
                            <button type="submit"
                                class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary/90 transition shadow-lg shadow-primary/30 flex items-center gap-2">
                                <span wire:loading.remove wire:target="import">Import Sekarang</span>
                                <span wire:loading wire:target="import" class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>