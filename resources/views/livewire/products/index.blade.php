<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Exceptions\PosException;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Imports\ProductsImport;
use App\Exports\ProductTemplateExport;
use App\Services\ProductService;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')]
    class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';
    // ?category= / ?brand= dari halaman Kategori & Brand
    #[\Livewire\Attributes\Url(as: 'category')]
    public $selectedCategory = '';
    #[\Livewire\Attributes\Url(as: 'brand')]
    public $selectedBrand = '';

    // ?lowStock=1 dari dashboard: hanya produk yang stoknya menipis di outlet aktif
    #[\Livewire\Attributes\Url]
    public $lowStock = false;
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

    public function updatedLowStock()
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

            $this->dispatch('notify', [
                'title' => 'Import Selesai dengan Catatan',
                'text' => "Sebagian data gagal diimpor. Silakan cek detailnya di layar.",
                'icon' => 'warning'
            ]);
        } else {
            $this->importMessageType = 'success';
            $this->importSuccess = "Sukses! Seluruh {$import->importedCount} data produk berhasil diimpor ke sistem.";
            app(\App\Services\ActivityLogger::class)->log('product.import', "Import produk Excel: {$import->importedCount} baris");
            $this->importMessage = null;
            $this->importFailedDetails = [];

            $this->closeImportModal();
            $this->dispatch('notify', [
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
                // Stok varian nonaktif tidak dihitung
                $query->where('outlet_id', $currentOutletId)
                    ->where(fn($q) => $q->whereNull('product_variant_id')->orWhereHas('variant', fn($v) => $v->where('is_active', true)));
            },
        ])->where(function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('sku', 'like', '%' . $this->search . '%')
                ->orWhere('barcode', 'like', '%' . $this->search . '%');
        });

        if ($this->selectedCategory !== '') {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->selectedBrand !== '') {
            $query->where('brand_id', $this->selectedBrand);
        }

        // Stok menipis dihitung per varian, sedangkan kolom stok menampilkan total semua varian.
        // Varian yang menipis ditampilkan terpisah agar jelas kenapa produknya ditandai.
        $lowRows = app(\App\Services\ReportService::class)
            ->lowStock((int) $currentOutletId, \App\Models\Setting::lowStockThreshold());

        if ($this->lowStock) {
            $query->whereIn('id', $lowRows->pluck('product_id')->unique());
        }

        return [
            'products' => $query->latest()->paginate(10),
            'lowVariants' => $lowRows->whereNotNull('product_variant_id')->groupBy('product_id'),
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }

    public function deleteProduct(ProductService $products, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            $this->dispatch('notify', [
                'title' => 'Gagal!',
                'text' => 'Produk tidak ditemukan di database.',
                'icon' => 'error'
            ]);
            return;
        }

        try {
            $products->delete($product);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $this->dispatch('notify', [
            'title' => 'Dihapus!',
            'text' => 'Produk beserta seluruh varian dan stoknya berhasil dihapus.',
            'icon' => 'success'
        ]);
    }
};
?>

@php
    $threshold = \App\Models\Setting::lowStockThreshold();
    $stockBadge = fn($qty, $lowList = null) => $qty <= 0 ? 'badge-danger' : ($qty <= $threshold || $lowList ? 'badge-warning' : 'badge-dark');
    $lowText = fn($lowList) => $lowList->take(3)->map(fn($r) => implode(' - ', array_filter([$r->size, $r->color])) . ': ' . $r->quantity)->implode(', ')
        . ($lowList->count() > 3 ? ' +' . ($lowList->count() - 3) . ' lainnya' : '');
@endphp

<div class="space-y-4">
    <x-ui.page-header title="Produk" subtitle="Kelola barang, harga, dan stok di satu tempat.">
        @if (auth()->user()->isOwner())
            <a href="/products/top" wire:navigate class="btn-secondary"><x-icon name="star" class="w-4 h-4" /> Terlaris</a>
        @endif
        <button wire:click="openImportModal" type="button" class="btn-secondary"><x-icon name="upload" class="w-4 h-4" /> Import Excel</button>
        <a href="/products/create" wire:navigate class="btn-primary"><x-icon name="plus" class="w-4 h-4" /> Tambah Produk</a>
    </x-ui.page-header>

    <!-- Filter -->
    <div class="tile !p-3 md:!p-4">
        <div class="grid grid-cols-2 lg:grid-cols-[1fr_12rem_12rem_auto] gap-2 md:gap-3">
            <div class="relative col-span-2 lg:col-span-1">
                <x-icon name="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama, SKU, atau barcode..." class="input !pl-11">
            </div>
            <select wire:model.live="selectedCategory" class="select">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="selectedBrand" class="select">
                <option value="">Semua Brand</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                @endforeach
            </select>
            <label class="{{ $lowStock ? 'chip-active' : 'chip' }} col-span-2 lg:col-span-1 whitespace-nowrap">
                <input type="checkbox" wire:model.live="lowStock" class="sr-only">
                <x-icon name="alert" class="w-4 h-4" /> Stok Menipis
            </label>
        </div>
    </div>

    <!-- Desktop: tabel -->
    <div class="tile-flush hidden lg:block">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Brand & Kategori</th>
                        <th class="text-right">Harga Jual</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $stockQty = $product->stocks->sum('quantity') ?? 0;
                            $lowList = $lowVariants->get($product->id);
                        @endphp
                        <tr wire:key="p-{{ $product->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($product->image)
                                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="w-11 h-11 rounded-2xl object-cover flex-shrink-0">
                                    @else
                                        <div class="w-11 h-11 rounded-2xl bg-zinc-100 flex items-center justify-center flex-shrink-0"><x-icon name="image" class="w-5 h-5 text-zinc-300" /></div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate max-w-xs">{{ $product->name }}</p>
                                        <p class="text-xs text-zinc-400 font-mono mt-0.5">
                                            {{ $product->sku ?? 'Bervarian' }}@if ($product->barcode) · {{ $product->barcode }}@endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if ($product->brand)
                                        <span class="badge-dark">{{ $product->brand->name }}</span>
                                    @endif
                                    <span class="badge-light">{{ $product->category->name ?? 'Tanpa Kategori' }}</span>
                                </div>
                            </td>
                            <td class="text-right font-semibold whitespace-nowrap">Rp {{ number_format($product->sell_price, 0, ',', '.') }}</td>
                            <td class="text-center">
                                <span class="{{ $stockBadge($stockQty, $lowList) }}">{{ $stockQty }}</span>
                                @if ($lowList)
                                    <p class="text-[11px] text-amber-700 mt-1 max-w-[11rem] mx-auto leading-snug">Menipis: {{ $lowText($lowList) }}</p>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="{{ $product->is_active ? 'badge-success' : 'badge-light' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <a href="/products/{{ $product->id }}/add-stock" wire:navigate class="btn-icon" title="Kelola Stok & Varian"><x-icon name="layers" class="w-4 h-4" /></a>
                                    <a href="/products/{{ $product->id }}/edit" wire:navigate class="btn-icon" title="Edit Produk"><x-icon name="pencil" class="w-4 h-4" /></a>
                                    <button type="button" x-data @click="confirmDeletion(() => $wire.deleteProduct({{ $product->id }}), @js('Produk ' . $product->name))"
                                        class="btn-icon hover:!text-red-600 hover:!bg-red-50" title="Hapus Produk"><x-icon name="trash" class="w-4 h-4" /></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty title="Belum ada data produk" text="Tambah produk baru atau import dari Excel." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="px-5 py-4 border-t border-zinc-100">{{ $products->links() }}</div>
        @endif
    </div>

    <!-- HP & tablet: kartu -->
    <div class="lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
        @forelse ($products as $product)
            @php
                $stockQty = $product->stocks->sum('quantity') ?? 0;
                $lowList = $lowVariants->get($product->id);
            @endphp
            <div class="tile !p-4" wire:key="pm-{{ $product->id }}">
                <div class="flex gap-3">
                    @if ($product->image)
                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="w-14 h-14 rounded-2xl object-cover flex-shrink-0">
                    @else
                        <div class="w-14 h-14 rounded-2xl bg-zinc-100 flex items-center justify-center flex-shrink-0"><x-icon name="image" class="w-5 h-5 text-zinc-300" /></div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold leading-snug">{{ $product->name }}</p>
                        <p class="text-xs text-zinc-400 font-mono">{{ $product->sku ?? 'Bervarian' }}</p>
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @if ($product->brand) <span class="badge-dark">{{ $product->brand->name }}</span> @endif
                            <span class="badge-light">{{ $product->category->name ?? 'Tanpa Kategori' }}</span>
                            @unless ($product->is_active) <span class="badge-light">Nonaktif</span> @endunless
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-zinc-100">
                    <div>
                        <p class="font-bold">Rp {{ number_format($product->sell_price, 0, ',', '.') }}</p>
                        <p class="text-xs text-zinc-500">Stok <span class="{{ $stockBadge($stockQty, $lowList) }} ml-1">{{ $stockQty }}</span></p>
                        @if ($lowList)
                            <p class="text-[11px] text-amber-700 mt-1 leading-snug">Menipis: {{ $lowText($lowList) }}</p>
                        @endif
                    </div>
                    <div class="flex gap-1">
                        <a href="/products/{{ $product->id }}/add-stock" wire:navigate class="btn-icon" title="Stok"><x-icon name="layers" class="w-4 h-4" /></a>
                        <a href="/products/{{ $product->id }}/edit" wire:navigate class="btn-icon" title="Edit"><x-icon name="pencil" class="w-4 h-4" /></a>
                        <button type="button" x-data @click="confirmDeletion(() => $wire.deleteProduct({{ $product->id }}), @js('Produk ' . $product->name))" class="btn-icon hover:!text-red-600" title="Hapus"><x-icon name="trash" class="w-4 h-4" /></button>
                    </div>
                </div>
            </div>
        @empty
            <div class="tile sm:col-span-2"><x-ui.empty title="Belum ada data produk" text="Tambah produk baru atau import dari Excel." /></div>
        @endforelse
        @if ($products->hasPages())
            <div class="tile !p-3 sm:col-span-2">{{ $products->links() }}</div>
        @endif
    </div>

    @if ($showImportModal)
        <x-ui.modal title="Import Data Produk" subtitle="Format .xlsx atau .csv, maksimal 5MB" close="closeImportModal">
            <button type="button" wire:click="downloadTemplate" class="btn-secondary w-full mb-4">
                <x-icon name="download" class="w-4 h-4" /> Download Template Excel
            </button>

            <form wire:submit="import" class="space-y-4">
                <div>
                    <input type="file" wire:model="file" accept=".xlsx,.csv"
                        class="w-full text-sm rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 px-3 py-4 cursor-pointer file:mr-3 file:rounded-xl file:border-0 file:bg-ink file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white">
                    @error('file') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                @if ($importSuccess)
                    <div class="rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm font-medium p-4">{{ $importSuccess }}</div>
                @endif

                @if ($importMessage)
                    <div class="rounded-2xl border p-4 text-sm {{ $importMessageType === 'warning' ? 'bg-amber-50 border-amber-100 text-amber-800' : 'bg-emerald-50 border-emerald-100 text-emerald-700' }}">
                        <p class="font-semibold">{{ $importMessage }}</p>
                        @if (count($importFailedDetails) > 0)
                            <ul class="space-y-1 max-h-32 overflow-y-auto mt-2">
                                @foreach ($importFailedDetails as $failedSku)
                                    <li class="bg-white rounded-xl px-3 py-2 text-xs font-mono">{{ $failedSku }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @if (count($importErrors) > 0)
                    <div class="rounded-2xl bg-red-50 border border-red-100 text-red-700 text-sm p-4 max-h-40 overflow-y-auto">
                        <p class="font-semibold">Peringatan format Excel:</p>
                        <ul class="space-y-1 mt-2">
                            @foreach ($importErrors as $error)
                                <li class="bg-white rounded-xl px-3 py-2 text-xs"><b>Baris {{ $error['row'] }}:</b> {{ implode(', ', $error['errors']) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-2 pt-2">
                    <button type="button" wire:click="closeImportModal" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">
                        <span wire:loading.remove wire:target="import">Import Data</span>
                        <span wire:loading wire:target="import">Memproses...</span>
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
