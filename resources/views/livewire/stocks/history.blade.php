<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\StockHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use App\Imports\StockImport;
use App\Imports\ReturImport;
use App\Exports\StockTemplateExport;
use App\Exports\ReturTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')]
    class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';
    public $type = '';
    public $startDate = '';
    public $endDate = '';

    public $showImportModal = false;
    public $file;
    public $importSuccess = null;
    public $importMessage = null;
    public $importMessageType = 'success';
    public $importFailedDetails = [];

    // Properti Modal Import Retur
    public $showReturModal = false;
    public $returFile;
    public $returSuccess = null;
    public $returMessage = null;
    public $returMessageType = 'success';
    public $returFailedDetails = [];

    public function updatedSearch()
    {
        $this->resetPage();
    }
    public function updatedType()
    {
        $this->resetPage();
    }
    public function updatedStartDate()
    {
        $this->resetPage();
    }
    public function updatedEndDate()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'type', 'startDate', 'endDate']);
        $this->resetPage();
    }

    public function openImportModal()
    {
        $this->showImportModal = true;
        $this->reset(['file', 'importSuccess', 'importMessage', 'importMessageType', 'importFailedDetails']);
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
    }

    public function downloadTemplate()
    {
        return Excel::download(new StockTemplateExport(), 'template-restock.xlsx');
    }

    public function import()
    {
        $this->validate(['file' => 'required|mimes:xlsx,csv|max:5120']);
        $outletId = session('current_outlet_id');
        $import = new StockImport($outletId);

        try {
            Excel::import($import, $this->file);
            $totalFailed = count($import->failedRows);

            if ($totalFailed > 0) {
                $this->importSuccess = null;
                $this->importMessageType = 'warning';
                $this->importMessage = "Proses Selesai! Berhasil masuk: {$import->importedCount} baris. Ditolak: {$totalFailed} baris.";
                $this->importFailedDetails = $import->failedRows;
                $this->dispatch('notify', ['title' => 'Selesai dengan Catatan', 'text' => "Sebagian baris ditolak.", 'icon' => 'warning']);
            } else {
                $this->importMessageType = 'success';
                $this->importSuccess = "Sukses! {$import->importedCount} data berhasil di-restock.";
                app(\App\Services\ActivityLogger::class)->log('stock.import', "Import restock Excel: {$import->importedCount} baris");
                $this->closeImportModal();
                $this->dispatch('notify', ['title' => 'Restock Berhasil!', 'text' => "Semua data masuk ke sistem.", 'icon' => 'success']);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', ['title' => 'Terjadi Kesalahan!', 'text' => 'Error: ' . $e->getMessage(), 'icon' => 'error']);
        }
        $this->reset('file');
    }

    // ==========================================
    // LOGIKA IMPORT RETUR (KELUAR)
    // ==========================================
    public function openReturModal()
    {
        $this->showReturModal = true;
        $this->reset(['returFile', 'returSuccess', 'returMessage', 'returMessageType', 'returFailedDetails']);
    }

    public function closeReturModal()
    {
        $this->showReturModal = false;
    }

    public function downloadReturTemplate()
    {
        return Excel::download(new ReturTemplateExport(), 'template-retur.xlsx');
    }

    public function importRetur()
    {
        $this->validate(['returFile' => 'required|mimes:xlsx,csv|max:5120']);
        $outletId = session('current_outlet_id');
        $import = new ReturImport($outletId);

        try {
            Excel::import($import, $this->returFile);
            $totalFailed = count($import->failedRows);

            if ($totalFailed > 0) {
                $this->returSuccess = null;
                $this->returMessageType = 'warning';
                $this->returMessage = "Proses Selesai! Berhasil ditarik: {$import->importedCount} baris. Ditolak (Stok Minus/Barcode Salah): {$totalFailed} baris.";
                $this->returFailedDetails = $import->failedRows;
                $this->dispatch('notify', ['title' => 'Selesai dengan Catatan', 'text' => "Ada stok yang tidak cukup.", 'icon' => 'warning']);
            } else {
                $this->returMessageType = 'success';
                $this->returSuccess = "Sukses! {$import->importedCount} data berhasil diretur.";
                app(\App\Services\ActivityLogger::class)->log('stock.return', "Import retur Excel: {$import->importedCount} baris");
                $this->closeReturModal();
                $this->dispatch('notify', ['title' => 'Retur Berhasil!', 'text' => "Stok berhasil dikurangi.", 'icon' => 'success']);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', ['title' => 'Terjadi Kesalahan!', 'text' => 'Error: ' . $e->getMessage(), 'icon' => 'error']);
        }
        $this->reset('returFile');
    }

    public function with(): array
    {
        $outletId = session('current_outlet_id');
        $query = StockHistory::with(['product', 'variant', 'user'])->where('outlet_id', $outletId);

        if (!empty($this->search)) {
            $query->where(function (Builder $q) {
                $q->where('nota_number', 'like', '%' . $this->search . '%')
                    ->orWhere('note', 'like', '%' . $this->search . '%')
                    ->orWhereHas('product', function (Builder $q) {
                        $q->where('name', 'like', '%' . $this->search . '%')->orWhere('sku', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('variant', function (Builder $q) {
                        $q->where('sku', 'like', '%' . $this->search . '%');
                    });
            });
        }
        if (in_array($this->type, ['in', 'out'])) {
            $query->whereIn('type', StockHistory::typesFor($this->type));
        } elseif (!empty($this->type)) {
            $query->where('type', $this->type);
        }
        if (!empty($this->startDate)) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if (!empty($this->endDate)) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        return ['histories' => $query->latest()->paginate(15)];
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Stok" subtitle="Lacak setiap pergerakan stok: restock, penjualan, retur, transfer, dan penyesuaian." />

    <!-- Aksi stok -->
    @php
        // [label, keterangan, ikon, aksi (url / method Livewire), tile utama]
        $actions = [
            ['Import Restock', 'Tambah stok dari Excel', 'upload', 'openImportModal', true],
            ['Import Retur', 'Kurangi stok dari Excel', 'arrow-up', 'openReturModal', false],
            ['Stock Opname', 'Samakan dengan hitung fisik', 'clipboard', '/stocks/opname', false],
            ['Transfer Stok', 'Pindah stok antar outlet', 'swap', '/stocks/transfer', false],
        ];
    @endphp
    <div class="bento grid-cols-2 lg:grid-cols-4">
        @foreach ($actions as [$label, $desc, $icon, $action, $primary])
            @php
                $classes = ($primary ? 'tile-dark hover:bg-ink-soft' : 'tile hover:border-zinc-400') . ' !p-4 md:!p-5 flex flex-col sm:flex-row items-start sm:items-center gap-3 text-left transition group';
            @endphp
            @if (str_starts_with($action, '/'))
                <a href="{{ $action }}" wire:navigate class="{{ $classes }}">
            @else
                <button type="button" wire:click="{{ $action }}" class="{{ $classes }}">
            @endif
                <div class="w-10 h-10 md:w-11 md:h-11 rounded-2xl flex items-center justify-center flex-shrink-0 {{ $primary ? 'bg-white/10' : 'bg-zinc-100' }}">
                    <x-icon :name="$icon" class="w-5 h-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold leading-tight">{{ $label }}</p>
                    <p class="text-[11px] mt-0.5 leading-snug {{ $primary ? 'text-white/50' : 'text-zinc-400' }}">{{ $desc }}</p>
                </div>
                <x-icon name="chevron-right" class="hidden sm:block w-4 h-4 flex-shrink-0 transition group-hover:translate-x-0.5 {{ $primary ? 'text-white/40' : 'text-zinc-300' }}" />
            @if (str_starts_with($action, '/'))
                </a>
            @else
                </button>
            @endif
        @endforeach
    </div>

    <div class="tile !p-3 md:!p-4">
        <div class="grid grid-cols-2 lg:grid-cols-[1fr_13rem_10rem_10rem_auto] gap-2 md:gap-3 items-end">
            <div class="relative col-span-2 lg:col-span-1">
                <x-icon name="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari produk, SKU, nota, catatan..." class="input !pl-11">
            </div>
            <select wire:model.live="type" class="select col-span-2 lg:col-span-1">
                <option value="">Semua Jenis</option>
                <option value="in">Semua Stok Masuk</option>
                <option value="out">Semua Stok Keluar</option>
                @foreach (\App\Models\StockHistory::TYPES as $code => $meta)
                    <option value="{{ $code }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="startDate" class="input" title="Dari tanggal">
            <input type="date" wire:model.live="endDate" class="input" title="Sampai tanggal">
            @if ($search || $type || $startDate || $endDate)
                <button wire:click="resetFilters" class="btn-ghost col-span-2 lg:col-span-1"><x-icon name="x" class="w-4 h-4" /> Reset</button>
            @endif
        </div>
    </div>

    <div class="tile-flush">
        <!-- Desktop -->
        <div class="overflow-x-auto hidden md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Referensi</th>
                        <th class="text-center">Jenis</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($histories as $history)
                        <tr wire:key="h-{{ $history->id }}">
                            <td class="whitespace-nowrap">
                                <p class="font-semibold">{{ $history->created_at->format('d M Y') }}</p>
                                <p class="text-xs text-zinc-400">{{ $history->created_at->format('H:i') }} · {{ $history->user->name ?? 'Sistem' }}</p>
                            </td>
                            <td>
                                <p class="font-semibold">{{ $history->product->name ?? 'Produk dihapus' }}</p>
                                <p class="text-xs text-zinc-400">
                                    @if ($history->variant)
                                        {{ $history->variant->size }} · {{ $history->variant->color }} · <span class="font-mono">{{ $history->variant->sku }}</span>
                                    @else
                                        <span class="font-mono">{{ $history->product->sku ?? '-' }}</span>
                                    @endif
                                </p>
                            </td>
                            <td>
                                @if ($history->nota_number)
                                    <span class="badge-light font-mono">{{ $history->nota_number }}</span>
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                                @if ($history->note)
                                    <p class="text-xs text-zinc-500 mt-1 max-w-[14rem] truncate" title="{{ $history->note }}">{{ $history->note }}</p>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="{{ $history->isIncoming() ? 'badge-success' : 'badge-danger' }}">{{ $history->typeLabel() }}</span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <p class="text-base font-bold {{ $history->isIncoming() ? 'text-emerald-600' : 'text-red-600' }}">{{ $history->isIncoming() ? '+' : '-' }}{{ $history->quantity }}</p>
                                @if ($history->stock_before !== null)
                                    <p class="text-[11px] text-zinc-400">{{ $history->stock_before }} → {{ $history->stock_after }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="clipboard" title="Belum ada riwayat stok" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- HP -->
        <div class="md:hidden divide-y divide-zinc-100">
            @forelse ($histories as $history)
                <div class="flex items-start gap-3 px-4 py-3.5" wire:key="hm-{{ $history->id }}">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 {{ $history->isIncoming() ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }}">
                        <x-icon :name="$history->isIncoming() ? 'arrow-down' : 'arrow-up'" class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-snug">{{ $history->product->name ?? 'Produk dihapus' }}</p>
                        <p class="text-xs text-zinc-400">
                            {{ $history->typeLabel() }}@if ($history->variant) · {{ $history->variant->size }} {{ $history->variant->color }}@endif
                            · {{ $history->created_at->format('d/m H:i') }}
                        </p>
                        @if ($history->nota_number || $history->note)
                            <p class="text-xs text-zinc-500 truncate">{{ $history->nota_number }} {{ $history->note }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold {{ $history->isIncoming() ? 'text-emerald-600' : 'text-red-600' }}">{{ $history->isIncoming() ? '+' : '-' }}{{ $history->quantity }}</p>
                        @if ($history->stock_before !== null)
                            <p class="text-[11px] text-zinc-400">{{ $history->stock_before }}→{{ $history->stock_after }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.empty icon="clipboard" title="Belum ada riwayat stok" />
            @endforelse
        </div>

        @if ($histories->hasPages())
            <div class="px-5 py-4 border-t border-zinc-100">{{ $histories->links() }}</div>
        @endif
    </div>

    @if ($showReturModal)
        <x-ui.modal title="Import Retur via Excel" subtitle="Stok akan dikurangi sesuai jumlah retur" close="closeReturModal">
            <button type="button" wire:click="downloadReturTemplate" class="btn-secondary w-full mb-4">
                <x-icon name="download" class="w-4 h-4" /> Download Template Retur
            </button>
            <form wire:submit="importRetur" class="space-y-4">
                <div>
                    <input type="file" wire:model="returFile" accept=".xlsx,.csv"
                        class="w-full text-sm rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 px-3 py-4 cursor-pointer file:mr-3 file:rounded-xl file:border-0 file:bg-ink file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white">
                    @error('returFile') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                @if ($returMessage)
                    <div class="rounded-2xl border p-4 text-sm {{ $returMessageType === 'warning' ? 'bg-amber-50 border-amber-100 text-amber-800' : 'bg-emerald-50 border-emerald-100 text-emerald-700' }}">
                        <p class="font-semibold">{{ $returMessage }}</p>
                        @if (count($returFailedDetails) > 0)
                            <ul class="space-y-1 max-h-32 overflow-y-auto mt-2">
                                @foreach ($returFailedDetails as $failed)
                                    <li class="bg-white rounded-xl px-3 py-2 text-xs font-mono">{{ $failed }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-2 pt-2">
                    <button type="button" wire:click="closeReturModal" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn bg-red-600 text-snow hover:bg-red-700">
                        <span wire:loading.remove wire:target="importRetur">Proses Retur</span>
                        <span wire:loading wire:target="importRetur">Memproses...</span>
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showImportModal)
        <x-ui.modal title="Import Restock via Excel" subtitle="Stok akan ditambahkan ke outlet aktif" close="closeImportModal">
            <button type="button" wire:click="downloadTemplate" class="btn-secondary w-full mb-4">
                <x-icon name="download" class="w-4 h-4" /> Download Template Restock
            </button>
            <form wire:submit="import" class="space-y-4">
                <div>
                    <input type="file" wire:model="file" accept=".xlsx,.csv"
                        class="w-full text-sm rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 px-3 py-4 cursor-pointer file:mr-3 file:rounded-xl file:border-0 file:bg-ink file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white">
                    @error('file') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                @if ($importMessage)
                    <div class="rounded-2xl border p-4 text-sm {{ $importMessageType === 'warning' ? 'bg-amber-50 border-amber-100 text-amber-800' : 'bg-emerald-50 border-emerald-100 text-emerald-700' }}">
                        <p class="font-semibold">{{ $importMessage }}</p>
                        @if (count($importFailedDetails) > 0)
                            <ul class="space-y-1 max-h-32 overflow-y-auto mt-2">
                                @foreach ($importFailedDetails as $failed)
                                    <li class="bg-white rounded-xl px-3 py-2 text-xs font-mono">{{ $failed }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
                <div class="grid grid-cols-2 gap-2 pt-2">
                    <button type="button" wire:click="closeImportModal" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">
                        <span wire:loading.remove wire:target="import">Proses Restock</span>
                        <span wire:loading wire:target="import">Memproses...</span>
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
