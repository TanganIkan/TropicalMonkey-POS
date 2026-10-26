<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Exceptions\PosException;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Stock;
use App\Services\ActivityLogger;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    // Hasil hitung fisik: ["{product_id}-{variant_id|single}" => angka]. Tersimpan lintas halaman.
    public $counts = [];

    public $note = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function getOutletProperty()
    {
        return Outlet::find(session('current_outlet_id'));
    }

    // Baris yang sudah diisi (bukan kosong)
    protected function filledCounts(): array
    {
        return array_filter($this->counts, fn($v) => $v !== '' && $v !== null);
    }

    public function clearCounts()
    {
        $this->reset('counts');
    }

    public function save(StockService $stocks, ActivityLogger $logger)
    {
        $filled = $this->filledCounts();

        if (empty($filled)) {
            $this->dispatch('notify', ['title' => 'Belum Ada Data', 'text' => 'Isi hasil hitung fisik minimal untuk 1 barang.', 'icon' => 'info']);
            return;
        }

        $rows = [];
        foreach ($filled as $key => $counted) {
            [$productId, $variantPart] = explode('-', $key, 2);
            $rows[] = [
                'product_id' => (int) $productId,
                'variant_id' => $variantPart === 'single' ? null : (int) $variantPart,
                'counted' => (int) $counted,
            ];
        }

        try {
            $changed = $stocks->opname($this->outlet->id, $rows, $this->note);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $logger->log('stock.opname', "Stock opname: " . count($rows) . " barang dihitung, {$changed} disesuaikan", properties: [
            'counted' => count($rows),
            'adjusted' => $changed,
        ]);

        $this->reset(['counts', 'note']);
        $this->dispatch('notify', [
            'title' => 'Stock Opname Tersimpan',
            'text' => "{$changed} dari " . count($rows) . " barang disesuaikan dengan hasil hitung fisik. Semua penyesuaian tercatat di Lacak Stok.",
            'icon' => 'success',
        ]);
    }

    public function with(): array
    {
        // Semua barang yang bisa dihitung: produk tanpa varian + varian aktif
        $items = Product::query()
            ->leftJoin('product_variants', fn($j) => $j->on('product_variants.product_id', '=', 'products.id')->where('product_variants.is_active', true))
            ->where(fn($q) => $q->where('products.has_variants', false)->orWhereNotNull('product_variants.id'))
            ->when($this->search, fn($q) => $q->where(fn($q) => $q
                ->where('products.name', 'like', "%{$this->search}%")
                ->orWhere('products.sku', 'like', "%{$this->search}%")
                ->orWhere('products.barcode', 'like', "%{$this->search}%")
                ->orWhere('product_variants.sku', 'like', "%{$this->search}%")
                ->orWhere('product_variants.barcode', 'like', "%{$this->search}%")))
            ->select(
                'products.id as product_id',
                'products.name',
                'product_variants.id as variant_id',
                'product_variants.size',
                'product_variants.color',
                DB::raw('COALESCE(product_variants.sku, products.sku) as sku'),
            )
            ->orderBy('products.name')
            ->orderBy('product_variants.size')
            ->orderBy('product_variants.color')
            ->paginate(25);

        $stocks = Stock::where('outlet_id', $this->outlet->id)
            ->whereIn('product_id', $items->pluck('product_id')->unique())
            ->get()
            ->keyBy(fn($s) => $s->product_id . '-' . ($s->product_variant_id ?? 'single'));

        return [
            'items' => $items,
            'stocks' => $stocks,
            'filledCount' => count($this->filledCounts()),
        ];
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Stock Opname" :subtitle="$this->outlet->name . ' · isi hasil hitung fisik. Hanya barang yang diisi yang disesuaikan.'" back="/stocks/history" back-label="Stok">
        <div class="relative w-full md:w-72">
            <x-icon name="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama / SKU / barcode..." class="input !pl-11">
        </div>
    </x-ui.page-header>

    <div class="tile-flush">
        <div class="divide-y divide-zinc-100">
            <div class="hidden md:grid grid-cols-[1fr_10rem_6rem_8rem_5rem] gap-4 px-5 py-3 bg-zinc-50 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                <span>Barang</span><span>SKU</span><span class="text-center">Sistem</span><span class="text-center">Hitung Fisik</span><span class="text-center">Selisih</span>
            </div>
            @forelse ($items as $item)
                @php
                    $key = $item->product_id . '-' . ($item->variant_id ?? 'single');
                    $system = $stocks->get($key)?->quantity ?? 0;
                    $counted = $counts[$key] ?? null;
                    $diff = ($counted !== null && $counted !== '') ? (int) $counted - $system : null;
                @endphp
                <div wire:key="op-{{ $key }}" class="grid grid-cols-[1fr_5.5rem] md:grid-cols-[1fr_10rem_6rem_8rem_5rem] gap-x-4 gap-y-1 items-center px-4 md:px-5 py-3 {{ $diff ? 'bg-amber-50/60' : '' }}">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate">{{ $item->name }}</p>
                        <p class="text-xs text-zinc-400">
                            @if ($item->variant_id) {{ $item->size }} · {{ $item->color }} @endif
                            <span class="md:hidden font-mono">{{ $item->sku ?? '' }} · sistem {{ $system }}</span>
                        </p>
                    </div>
                    <span class="hidden md:block font-mono text-xs text-zinc-500 truncate">{{ $item->sku ?? '-' }}</span>
                    <span class="hidden md:block text-center font-semibold">{{ $system }}</span>
                    <div class="row-span-2 md:row-span-1">
                        <input type="number" min="0" wire:model.live.debounce.400ms="counts.{{ $key }}" placeholder="-" class="input !py-1.5 text-center font-semibold">
                    </div>
                    <span class="text-xs md:text-sm md:text-center font-bold {{ $diff > 0 ? 'text-emerald-600' : ($diff < 0 ? 'text-red-600' : 'text-zinc-300') }}">
                        {{ $diff === null ? '' : ($diff > 0 ? '+' . $diff : $diff) }}
                    </span>
                </div>
            @empty
                <x-ui.empty icon="search" title="Tidak ada barang ditemukan" />
            @endforelse
        </div>
        <div class="px-5 py-4 border-t border-zinc-100">{{ $items->links() }}</div>
    </div>

    <!-- Bar simpan -->
    <div class="sticky bottom-24 lg:bottom-4 z-20">
        <div class="tile-dark !p-3 md:!p-4 flex flex-col md:flex-row gap-3 md:items-center shadow-lift">
            <p class="text-sm font-semibold whitespace-nowrap px-1">{{ $filledCount }} barang diisi</p>
            <input type="text" wire:model="note" placeholder="Catatan opname (opsional), mis. Opname akhir bulan"
                class="flex-1 rounded-2xl bg-white/10 border border-white/10 px-4 py-2.5 text-sm text-white placeholder:text-white/40 outline-none focus:border-white/40">
            <div class="flex gap-2">
                @if ($filledCount > 0)
                    <button type="button" wire:click="clearCounts" class="btn text-white/70 hover:text-white">Kosongkan</button>
                @endif
                <button type="button" x-data
                    @click="confirmAction(() => $wire.save(), 'Simpan stock opname?', 'Stok sistem akan disamakan dengan hasil hitung fisik untuk {{ $filledCount }} barang.', 'Ya, Simpan')"
                    class="btn bg-white text-ink hover:bg-zinc-100 flex-1 md:flex-none">Simpan Opname</button>
            </div>
        </div>
    </div>
</div>
