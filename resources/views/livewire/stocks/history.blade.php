<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\StockHistory;
use Illuminate\Database\Eloquent\Builder;
use App\Imports\StockImport;
use App\Exports\StockTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';
    public $type = '';
    public $startDate = '';
    public $endDate = '';

    // Properti Modal Import
    public $showImportModal = false;
    public $file;
    public $importSuccess = null;
    public $importMessage = null;
    public $importMessageType = 'success';
    public $importFailedDetails = [];

    public function updatedSearch() { $this->resetPage(); }
    public function updatedType() { $this->resetPage(); }
    public function updatedStartDate() { $this->resetPage(); }
    public function updatedEndDate() { $this->resetPage(); }

    public function resetFilters()
    {
        $this->reset(['search', 'type', 'startDate', 'endDate']);
        $this->resetPage();
    }

    // --- FITUR IMPORT EXCEL RESTOCK ---
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
        return Excel::download(new StockTemplateExport(), 'template-restock-stok.xlsx');
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

                $this->dispatch('swal', [
                    'title' => 'Import Selesai dengan Catatan',
                    'text' => "Sebagian nota atau SKU ditolak. Silakan cek detailnya.",
                    'icon' => 'warning'
                ]);
            } else {
                $this->importMessageType = 'success';
                $this->importSuccess = "Sukses! Seluruh {$import->importedCount} data stok berhasil di-restock.";
                $this->importMessage = null;
                $this->importFailedDetails = [];

                $this->closeImportModal();
                $this->dispatch('swal', [
                    'title' => 'Restock Berhasil!',
                    'text' => "Seluruh {$import->importedCount} data masuk ke sistem.",
                    'icon' => 'success'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Terjadi Kesalahan!',
                'text' => 'Gagal memproses file: ' . $e->getMessage(),
                'icon' => 'error'
            ]);
        }

        $this->reset('file');
    }

    public function with(): array
    {
        $outletId = session('current_outlet_id');

        $query = StockHistory::with(['product', 'variant'])
            ->where('outlet_id', $outletId);

        if (!empty($this->search)) {
            $query->where(function (Builder $q) {
                $q->where('nota_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('product', function (Builder $q) {
                      $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('variant', function (Builder $q) {
                      $q->where('sku', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if (!empty($this->type)) {
            $query->where('type', $this->type);
        }

        if (!empty($this->startDate)) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if (!empty($this->endDate)) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        return [
            'histories' => $query->latest()->paginate(15),
        ];
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 flex flex-col space-y-4 md:space-y-6 bg-gray-50/30 min-h-screen">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 flex-shrink-0 mb-2">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 flex items-center gap-3 tracking-tight">
                Riwayat Stok Produk
            </h1>
            <p class="text-sm text-gray-500 mt-1">Lacak semua pergerakan barang masuk dan keluar di toko ini.</p>
        </div>
        
        <div class="flex gap-3">
            <!-- TOMBOL IMPORT RESTOCK BARU -->
            <button wire:click="openImportModal" type="button" class="bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-primary/90 transition shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                Import Restock
            </button>
        </div>
    </div>

    <!-- Filter & Table Card -->
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col overflow-hidden">
        
        <div class="p-4 lg:p-5 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row gap-4">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari No. Nota, SKU, atau Nama Barang..."
                    class="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition text-sm font-medium">
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <select wire:model.live="type" class="w-full sm:w-40 px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary outline-none transition text-sm cursor-pointer font-medium text-gray-700">
                    <option value="">Semua Status</option>
                    <option value="in">Stok Masuk</option>
                    <option value="out">Stok Keluar</option>
                </select>
                <div class="relative w-full sm:w-44">
                    <input type="date" wire:model.live="startDate" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary outline-none transition text-sm font-medium text-gray-700">
                </div>
                <span class="hidden sm:flex items-center text-gray-400 font-bold">-</span>
                <div class="relative w-full sm:w-44">
                    <input type="date" wire:model.live="endDate" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary outline-none transition text-sm font-medium text-gray-700">
                </div>
                @if($search || $type || $startDate || $endDate)
                <button wire:click="resetFilters" class="px-4 py-2.5 bg-red-50 text-red-600 rounded-xl font-bold hover:bg-red-100 transition text-sm flex items-center justify-center gap-1">Reset</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-white border-b border-gray-200">
                    <tr class="text-[11px] uppercase tracking-wider text-gray-500 font-bold">
                        <th class="px-6 py-4">Tanggal & Waktu</th>
                        <th class="px-6 py-4">Referensi / Nota</th>
                        <th class="px-6 py-4">Produk & Varian</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($histories as $history)
                        <tr class="hover:bg-gray-50/80 transition group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm font-bold text-gray-900">{{ $history->created_at->format('d M Y') }}</p>
                                <p class="text-xs text-gray-500 font-medium">{{ $history->created_at->format('H:i') }} WITA</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($history->nota_number)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        {{ $history->nota_number }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400 font-medium italic">Sistem / Manual</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm font-bold text-gray-900">{{ $history->product->name ?? 'Produk Dihapus' }}</p>
                                @if($history->variant)
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Varian: <span class="font-bold text-gray-700">{{ $history->variant->size }} - {{ $history->variant->color }}</span> 
                                        <span class="mx-1">|</span> SKU: {{ $history->variant->sku }}
                                    </p>
                                @else
                                    <p class="text-xs text-gray-500 mt-0.5">SKU: {{ $history->product->sku ?? '-' }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($history->type === 'in')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200 shadow-sm">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div> MASUK
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200 shadow-sm">
                                        <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div> KELUAR
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if($history->type === 'in')
                                    <span class="text-lg font-black text-green-600 tracking-tight">+ {{ $history->quantity }}</span>
                                @else
                                    <span class="text-lg font-black text-red-600 tracking-tight">- {{ $history->quantity }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center text-gray-400">Belum ada riwayat stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($histories->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">{{ $histories->links() }}</div>
        @endif
    </div>

    <!-- MODAL IMPORT RESTOCK -->
  @if ($showImportModal)
        <template x-teleport="body">
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center z-[60] p-4 transition-opacity" wire:click.self="closeImportModal">
                
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" wire:click.stop>
                    
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/80">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            Import Restock via Excel
                        </h2>
                        <button wire:click="closeImportModal" class="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-200 rounded-full transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="p-6 max-h-[80vh] overflow-y-auto">
                        <button type="button" wire:click="downloadTemplate" class="w-full text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 border-2 border-gray-200 rounded-xl px-4 py-3.5 mb-6 transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Download Template Restock
                        </button>

                        <form wire:submit="import">
                            <div class="mb-6">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Upload File (.xlsx, .csv)</label>
                                <input type="file" wire:model="file" accept=".xlsx,.csv" class="w-full text-sm border-2 border-gray-200 border-dashed rounded-xl px-3 py-4 bg-gray-50/50 focus:outline-none focus:border-gray-900 transition file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-gray-900 file:text-white hover:file:bg-black cursor-pointer">
                                @error('file') <span class="text-red-500 text-xs mt-2 block font-medium">{{ $message }}</span> @enderror
                            </div>

                            @if ($importSuccess)
                                <div class="bg-green-50 border border-green-200 text-green-700 text-sm p-4 rounded-xl mb-4 font-bold flex items-start gap-2">
                                    {{ $importSuccess }}
                                </div>
                            @endif

                            @if ($importMessage)
                                <div class="{{ $importMessageType === 'warning' ? 'bg-orange-50 border-orange-200 text-orange-800' : 'bg-green-50 border-green-200 text-green-700' }} border text-sm p-4 rounded-xl mb-4">
                                    <p class="font-bold mb-2">{{ $importMessage }}</p>
                                    @if(count($importFailedDetails) > 0)
                                        <ul class="space-y-1 max-h-32 overflow-y-auto mt-2">
                                            @foreach($importFailedDetails as $failed)
                                                <li class="bg-white p-2 rounded-lg text-xs font-mono border border-orange-100">{{ $failed }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif

                            <div class="flex gap-3 justify-end mt-8">
                                <button type="button" wire:click="closeImportModal" class="px-5 py-2.5 text-gray-700 font-bold hover:bg-gray-100 rounded-xl text-sm transition bg-white border border-gray-200 shadow-sm w-full">Batal</button>
                                <button type="submit" class="px-5 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-bold hover:bg-black transition shadow-lg shadow-gray-900/20 w-full">
                                    <span wire:loading.remove wire:target="import">Proses Restock</span>
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