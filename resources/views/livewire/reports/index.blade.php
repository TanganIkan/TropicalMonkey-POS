<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Exports\BrandStockExport;
use App\Models\Outlet;
use App\Services\ReportService;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')] class extends Component {
    #[Url]
    public $tab = 'profit'; // profit | cashiers | inventory

    public $startDate = '';
    public $endDate = '';
    public $outlet = '';

    // Tab stok: posisi stok per tanggal (default hari ini)
    public $stockDate = '';
    public $includeZero = false;

    public function mount()
    {
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
        $this->stockDate = now()->toDateString();
    }

    // Tanggal di masa lalu = stok dihitung mundur sampai akhir hari tersebut
    protected function stockAsOf(): ?Carbon
    {
        $date = Carbon::parse($this->stockDate ?: now())->endOfDay();

        return $date->isToday() || $date->isFuture() ? null : $date;
    }

    protected function stockData(): array
    {
        $reports = app(ReportService::class);
        $snapshot = $reports->stockSnapshot($this->stockAsOf(), $this->outlet);
        $outletNames = Outlet::whereIn('id', $snapshot->pluck('outlet_id')->unique())->orderBy('name')->pluck('name', 'id')->all();

        return [
            'brands' => $reports->stockByBrand($snapshot, $this->includeZero),
            'outlets' => $outletNames,
            'byOutlet' => collect($outletNames)->map(fn($name, $id) => [
                'outlet_name' => $name,
                'qty' => (int) $snapshot->where('outlet_id', $id)->sum('quantity'),
                'cost_value' => $snapshot->where('outlet_id', $id)->sum(fn($r) => $r->quantity * $r->cost_price),
                'sell_value' => $snapshot->where('outlet_id', $id)->sum(fn($r) => $r->quantity * $r->sell_price),
            ])->values()->all(),
            'dateLabel' => Carbon::parse($this->stockDate ?: now())->translatedFormat('d F Y') . ($this->stockAsOf() ? '' : ' (saat ini)'),
        ];
    }

    public function exportStock()
    {
        $data = $this->stockData();

        app(\App\Services\ActivityLogger::class)->log('stock.export', "Export sisa stok per brand per {$data['dateLabel']}");

        return Excel::download(
            new BrandStockExport($data['brands'], $data['outlets'], $data['dateLabel']),
            'sisa-stok-per-brand-' . Carbon::parse($this->stockDate ?: now())->format('Y-m-d') . '.xlsx',
        );
    }

    public function with(): array
    {
        $reports = app(ReportService::class);
        $start = Carbon::parse($this->startDate ?: now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($this->endDate ?: now())->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [
            'outlets' => Outlet::orderBy('name')->get(['id', 'name']),
            'profit' => $this->tab === 'profit' ? $reports->profit($start, $end, $this->outlet) : null,
            'cashiers' => $this->tab === 'cashiers' ? $reports->cashiers($start, $end, $this->outlet) : null,
            'stock' => $this->tab === 'inventory' ? $this->stockData() : null,
        ];
    }
};
?>

@php
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $tabs = ['profit' => ['Laba Kotor', 'trending'], 'cashiers' => ['Kinerja Kasir', 'users'], 'inventory' => ['Sisa Stok & Persediaan', 'box']];
@endphp

<div class="space-y-4">
    <x-ui.page-header title="Laporan" subtitle="Laba, kinerja kasir, dan sisa stok. Transaksi void tidak dihitung." />

    <!-- Tab & filter -->
    <div class="tile !p-3 md:!p-4 space-y-3">
        <div class="flex gap-2 overflow-x-auto -mx-1 px-1 pb-1">
            @foreach ($tabs as $key => [$label, $icon])
                <button type="button" wire:click="$set('tab', '{{ $key }}')" class="{{ $tab === $key ? 'chip-active' : 'chip' }} whitespace-nowrap">
                    <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="grid grid-cols-2 lg:flex gap-2 md:gap-3">
            <select wire:model.live="outlet" class="select col-span-2 lg:!w-56">
                <option value="">Semua Outlet</option>
                @foreach ($outlets as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
            </select>
            @if ($tab !== 'inventory')
                <input type="date" wire:model.live="startDate" class="input lg:!w-44" title="Dari tanggal">
                <input type="date" wire:model.live="endDate" class="input lg:!w-44" title="Sampai tanggal">
            @else
                <input type="date" wire:model.live="stockDate" max="{{ now()->toDateString() }}" title="Posisi stok per tanggal" class="input lg:!w-44">
                <button type="button" wire:click="exportStock" wire:loading.attr="disabled" class="btn-primary lg:ml-auto">
                    <x-icon name="download" class="w-4 h-4" />
                    <span wire:loading.remove wire:target="exportStock">Export Sisa Stok (Excel)</span>
                    <span wire:loading wire:target="exportStock">Menyiapkan...</span>
                </button>
            @endif
        </div>
    </div>

    @if ($tab === 'profit')
        <div class="bento grid-cols-2 lg:grid-cols-4">
            <div class="tile-dark col-span-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Laba Kotor</p>
                <p class="text-3xl md:text-4xl font-bold tracking-tight mt-1 truncate {{ $profit['profit'] < 0 ? 'text-red-300' : '' }}">{{ $rp($profit['profit']) }}</p>
                <p class="text-xs text-white/50 mt-1">Margin {{ number_format($profit['margin'], 1, ',', '.') }}%</p>
            </div>
            <div class="tile">
                <p class="stat-label">Pendapatan</p>
                <p class="stat-value !text-xl md:!text-2xl">{{ $rp($profit['revenue']) }}</p>
            </div>
            <div class="tile">
                <p class="stat-label">HPP</p>
                <p class="stat-value !text-xl md:!text-2xl">{{ $rp($profit['cogs']) }}</p>
            </div>
        </div>
        <div class="tile-flush">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr><th>Brand</th><th class="text-right">Qty</th><th class="text-right">Pendapatan</th><th class="text-right">HPP</th><th class="text-right">Laba Kotor</th><th class="text-right">Margin</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($profit['by_brand'] as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['brand_name'] }}</td>
                                <td class="text-right">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                                <td class="text-right whitespace-nowrap">{{ $rp($row['revenue']) }}</td>
                                <td class="text-right whitespace-nowrap text-zinc-500">{{ $rp($row['cogs']) }}</td>
                                <td class="text-right whitespace-nowrap font-semibold {{ $row['profit'] < 0 ? 'text-red-600' : '' }}">{{ $rp($row['profit']) }}</td>
                                <td class="text-right">{{ number_format($row['margin'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty icon="trending" title="Belum ada penjualan di periode ini" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-zinc-400 px-1">HPP diambil dari harga modal saat barang terjual. Transaksi sebelum fitur ini ada memakai harga modal produk saat ini.</p>
    @elseif ($tab === 'cashiers')
        <div class="bento grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($cashiers as $i => $row)
                <div class="{{ $i === 0 ? 'tile-dark' : 'tile' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center text-xs font-bold {{ $i === 0 ? 'bg-white text-ink' : 'bg-zinc-100' }}">{{ $i + 1 }}</div>
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $row['user']->name ?? '-' }}</p>
                            <p class="text-xs {{ $i === 0 ? 'text-white/50' : 'text-zinc-400' }}">{{ $row['transactions'] }} transaksi</p>
                        </div>
                    </div>
                    <p class="text-2xl font-bold tracking-tight mt-4 truncate">{{ $rp($row['net_sales']) }}</p>
                    <div class="grid grid-cols-3 gap-2 mt-4 text-xs {{ $i === 0 ? 'text-white/60' : 'text-zinc-500' }}">
                        <div><p>Rata-rata</p><p class="font-semibold {{ $i === 0 ? 'text-white' : 'text-zinc-900' }}">{{ $rp($row['average']) }}</p></div>
                        <div><p>Diskon</p><p class="font-semibold {{ $i === 0 ? 'text-white' : 'text-zinc-900' }}">{{ $rp($row['discount']) }}</p></div>
                        <div><p>Void</p><p class="font-semibold {{ $row['voids'] ? 'text-red-500' : ($i === 0 ? 'text-white' : 'text-zinc-900') }}">{{ $row['voids'] }}</p></div>
                    </div>
                </div>
            @empty
                <div class="tile col-span-full"><x-ui.empty icon="users" title="Belum ada transaksi di periode ini" /></div>
            @endforelse
        </div>
    @else
        @php
            $brands = collect($stock['brands']);
            $byOutlet = collect($stock['byOutlet']);
        @endphp
        <p class="text-sm text-zinc-600 px-1">Posisi stok per <b>{{ $stock['dateLabel'] }}</b>.
            @if ($stockDate !== now()->toDateString()) Dihitung mundur dari stok sekarang memakai riwayat mutasi stok. @endif
        </p>

        <div class="bento grid-cols-1 sm:grid-cols-3">
            <div class="tile-dark">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Total Unit</p>
                <p class="text-3xl font-bold tracking-tight mt-1">{{ number_format($byOutlet->sum('qty'), 0, ',', '.') }}</p>
            </div>
            <div class="tile">
                <p class="stat-label">Nilai (HPP)</p>
                <p class="stat-value !text-xl md:!text-2xl">{{ $rp($byOutlet->sum('cost_value')) }}</p>
            </div>
            <div class="tile">
                <p class="stat-label">Nilai (Harga Jual)</p>
                <p class="stat-value !text-xl md:!text-2xl">{{ $rp($byOutlet->sum('sell_value')) }}</p>
            </div>
        </div>

        <div class="tile-flush">
            <div class="flex items-center justify-between gap-3 px-4 md:px-5 py-3.5 border-b border-zinc-100">
                <h2 class="tile-title">Sisa Stok per Brand</h2>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-zinc-600 cursor-pointer">
                    <input type="checkbox" wire:model.live="includeZero" class="checkbox"> Sertakan stok 0
                </label>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th class="text-right">Barang</th>
                            @foreach ($stock['outlets'] as $name) <th class="text-right">{{ $name }}</th> @endforeach
                            <th class="text-right">Total Stok</th>
                            <th class="text-right">Nilai (HPP)</th>
                            <th class="text-right">Nilai (Harga Jual)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($brands as $brand)
                            <tr>
                                <td class="font-semibold">{{ $brand['brand_name'] }}</td>
                                <td class="text-right">{{ count($brand['items']) }}</td>
                                @foreach ($stock['outlets'] as $outletId => $name)
                                    <td class="text-right text-zinc-500">{{ number_format(collect($brand['items'])->sum(fn($i) => $i['outlets'][$outletId] ?? 0), 0, ',', '.') }}</td>
                                @endforeach
                                <td class="text-right font-semibold">{{ number_format($brand['qty'], 0, ',', '.') }}</td>
                                <td class="text-right whitespace-nowrap">{{ $rp($brand['cost_value']) }}</td>
                                <td class="text-right whitespace-nowrap">{{ $rp($brand['sell_value']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 5 + count($stock['outlets']) }}"><x-ui.empty icon="box" title="Tidak ada stok" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-zinc-400 px-1">File Excel berisi sheet Ringkasan dan satu sheet per brand dengan rincian stok setiap produk/varian per outlet.</p>
    @endif
</div>
