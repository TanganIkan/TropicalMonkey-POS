<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Services\ReportService;
use App\Exports\BrandRevenueExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('components.layouts.app')]
    class extends Component {
    public $salesFilter = 'daily';
    public $selectedOutlet = '';
    public $customStart = null;
    public $customEnd = null;

    public function mount()
    {
        if (auth()->user()->role !== 'owner') {
            $this->redirect('/pos', navigate: true);
        }
    }

    protected function reports(): ReportService
    {
        return app(ReportService::class);
    }

    protected function getTrendRange()
    {
        return $this->reports()->range($this->salesFilter, $this->customStart, $this->customEnd);
    }

    public function applyCustomRange()
    {
        $this->validate(
            [
                'customStart' => 'required|date',
                'customEnd' => 'required|date|after_or_equal:customStart',
            ],
            [
                'customStart.required' => 'Tanggal mulai wajib dipilih.',
                'customEnd.required' => 'Tanggal akhir wajib dipilih.',
                'customEnd.after_or_equal' => 'Tidak boleh sebelum tanggal mulai.',
            ]
        );

        $this->salesFilter = 'custom';
        $this->dispatchChartData();
    }

    public function getSalesTrendDataProperty()
    {
        [$start, $end, $groupType] = $this->getTrendRange();

        return $this->reports()->salesTrend($start, $end, $groupType, $this->selectedOutlet);
    }

    public function getPaymentSlicesProperty()
    {
        [$start, $end] = $this->getTrendRange();

        return $this->reports()->paymentSlices($start, $end, $this->selectedOutlet);
    }

    public function getNonCashPercentageProperty()
    {
        return $this->reports()->nonCashPercentage($this->paymentSlices);
    }

    public function with(): array
    {
        [$start, $end] = $this->getTrendRange();
        // Stok menipis: outlet yang dipilih, atau outlet aktif bila "Semua Outlet"
        $stockOutletId = $this->selectedOutlet ?: session('current_outlet_id');

        return $this->reports()->summary($start, $end, $this->selectedOutlet) + [
            'recentTransactions' => $this->reports()->transactions($start, $end, $this->selectedOutlet)
                ->with('items')->latest()->limit(4)->get(),
            'lowStock' => $this->reports()->lowStock((int) $stockOutletId, \App\Models\Setting::lowStockThreshold(), 8),
            'lowStockOutlet' => $this->reports()->outletName($stockOutletId),
        ];
    }

    public function setFilter($filter)
    {
        $this->salesFilter = $filter;
        $this->dispatchChartData();
    }

    public function updatedSelectedOutlet()
    {
        $this->dispatchChartData();
    }

    protected function dispatchChartData()
    {
        $this->dispatch('sales-trend-updated', chart: $this->salesTrendData);
    }

    public function exportPdf()
    {
        [$start, $end] = $this->getTrendRange();

        $pdf = Pdf::loadView('exports.dashboard-report', $this->reports()->salesReport($start, $end, $this->selectedOutlet));

        return response()->streamDownload(fn() => print $pdf->output(), 'laporan-penjualan-' . now()->format('Ymd-His') . '.pdf');
    }

    public function exportBrandRevenue()
    {
        [$start, $end] = $this->getTrendRange();

        return Excel::download(
            new BrandRevenueExport(
                $this->reports()->brandRevenue($start, $end, $this->selectedOutlet),
                $this->reports()->periodLabel($start, $end),
                $this->reports()->outletName($this->selectedOutlet),
            ),
            'laporan-pendapatan-brand-' . now()->format('Ymd-His') . '.xlsx'
        );
    }
};
?>

@php
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $periods = ['daily' => '7 Hari', 'monthly' => '12 Bulan', 'yearly' => '5 Tahun'];
@endphp

<div class="space-y-4">
    <x-ui.page-header title="Dashboard" subtitle="Ringkasan performa toko pada periode terpilih.">
        <select wire:model.live="selectedOutlet" class="select !w-auto !py-2">
            <option value="">Semua Outlet</option>
            @foreach (\App\Models\Outlet::where('is_active', true)->get() as $outlet)
                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
            @endforeach
        </select>
        <button wire:click="exportPdf" wire:loading.attr="disabled" class="btn-secondary !py-2">
            <x-icon name="download" class="w-4 h-4" />
            <span wire:loading.remove wire:target="exportPdf">PDF</span>
            <span wire:loading wire:target="exportPdf">...</span>
        </button>
        <button wire:click="exportBrandRevenue" wire:loading.attr="disabled" class="btn-primary !py-2">
            <x-icon name="download" class="w-4 h-4" />
            <span wire:loading.remove wire:target="exportBrandRevenue">Excel per Brand</span>
            <span wire:loading wire:target="exportBrandRevenue">...</span>
        </button>
    </x-ui.page-header>

    <!-- Periode -->
    <div class="flex flex-col lg:flex-row lg:items-center gap-2 {{ $errors->hasAny(['customStart', 'customEnd']) ? 'sm:pb-6' : '' }}">
        <div class="flex gap-2 overflow-x-auto -mx-1 px-1 pb-1 lg:pb-0">
            @foreach ($periods as $key => $label)
                <button wire:click="setFilter('{{ $key }}')" class="{{ $salesFilter === $key ? 'chip-active' : 'chip' }} whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </div>
        <!-- Pesan error di bawah kolomnya masing-masing; di layar lebar melayang agar baris tidak ikut bergeser -->
        <div class="lg:ml-auto">
            <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
                @foreach (['customStart' => 'Dari tanggal', 'customEnd' => 'Sampai tanggal'] as $field => $title)
                    @if ($loop->last) <span class="hidden sm:inline text-zinc-300">—</span> @endif
                    <div class="relative">
                        <input type="date" wire:model="{{ $field }}" class="input sm:!w-auto !py-2 {{ $errors->has($field) ? '!border-red-500' : ($salesFilter === 'custom' ? '!border-ink' : '') }}" title="{{ $title }}">
                        @error($field) <span class="field-error px-1 sm:absolute sm:left-0 sm:top-full sm:whitespace-nowrap">{{ $message }}</span> @enderror
                    </div>
                @endforeach
                <button wire:click="applyCustomRange" class="{{ $salesFilter === 'custom' ? 'btn-primary' : 'btn-secondary' }} !py-2 col-span-2">Terapkan</button>
            </div>
        </div>
    </div>

    <div class="bento grid-cols-2 md:grid-cols-4 xl:grid-cols-12">
        <!-- Pendapatan -->
        <div class="tile-dark col-span-2 xl:col-span-4 flex flex-col justify-between min-h-[10rem]">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Total Pendapatan</p>
                <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center"><x-icon name="trending" class="w-4 h-4" /></div>
            </div>
            <div>
                <p class="text-3xl md:text-4xl font-bold tracking-tight truncate" title="{{ $rp($totalRevenue) }}">{{ $rp($totalRevenue) }}</p>
                <p class="text-xs text-white/50 mt-1">
                    {{ $totalDiscount > 0 ? 'Setelah diskon ' . $rp($totalDiscount) . ' · tanpa pajak' : 'Tanpa pajak' }}
                </p>
            </div>
        </div>

        <div class="tile col-span-1 xl:col-span-2">
            <div class="w-9 h-9 rounded-xl bg-zinc-100 flex items-center justify-center mb-4"><x-icon name="receipt" class="w-4 h-4" /></div>
            <p class="stat-label">Transaksi</p>
            <p class="stat-value">{{ number_format($totalTransactions, 0, ',', '.') }}</p>
        </div>

        <div class="tile col-span-1 xl:col-span-2">
            <div class="w-9 h-9 rounded-xl bg-zinc-100 flex items-center justify-center mb-4"><x-icon name="bag" class="w-4 h-4" /></div>
            <p class="stat-label">Item Terjual</p>
            <p class="stat-value">{{ number_format($totalItemsSold, 0, ',', '.') }}</p>
        </div>

        <div class="tile col-span-1 md:col-span-2 xl:col-span-2">
            <div class="w-9 h-9 rounded-xl bg-zinc-100 flex items-center justify-center mb-4"><x-icon name="percent" class="w-4 h-4" /></div>
            <p class="stat-label">Total Pajak</p>
            <p class="stat-value !text-xl md:!text-2xl" title="{{ $rp($taxNonLokal ?? 0) }}">{{ $rp($taxNonLokal ?? 0) }}</p>
        </div>

        <div class="tile col-span-1 md:col-span-2 xl:col-span-2">
            <div class="w-9 h-9 rounded-xl bg-zinc-100 flex items-center justify-center mb-4"><x-icon name="star" class="w-4 h-4" /></div>
            <p class="stat-label">Produk Teratas</p>
            <p class="text-base font-semibold mt-1 line-clamp-2" title="{{ $topProduct->product_name ?? 'Belum ada data' }}">{{ $topProduct->product_name ?? 'Belum ada data' }}</p>
        </div>

        <!-- Tren penjualan -->
        <div class="tile col-span-2 md:col-span-4 xl:col-span-8 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="tile-title">Tren Penjualan</h2>
                    <p class="tile-sub">Pendapatan per periode</p>
                </div>
                <span class="badge-light">{{ $periods[$salesFilter] ?? 'Rentang khusus' }}</span>
            </div>
            <div class="relative w-full h-[220px] md:h-[280px] mt-4" wire:ignore x-data="{
                chart: null,
                isBuilding: false,
                labels: [],
                data: [],
                buildChart(labels, data) {
                    this.labels = labels;
                    this.data = data;
                    if (!this.$refs.canvas) return;
                    if (this.isBuilding) return;
                    this.isBuilding = true;
                    if (this.chart) { this.chart.destroy(); this.chart = null; }
                    this.$nextTick(() => {
                        const ctx = this.$refs.canvas.getContext('2d');
                        const ink = window.themeColor('ink');
                        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
                        gradient.addColorStop(0, window.themeColor('ink', 0.14));
                        gradient.addColorStop(1, window.themeColor('ink', 0));
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Pendapatan',
                                    data: data,
                                    borderColor: ink,
                                    backgroundColor: gradient,
                                    borderWidth: 2.5,
                                    tension: 0.4,
                                    fill: true,
                                    pointBackgroundColor: window.themeColor('white'),
                                    pointBorderColor: ink,
                                    pointBorderWidth: 2,
                                    pointRadius: 3,
                                    pointHoverRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                layout: { padding: { left: 6, right: 6, top: 10, bottom: 0 } },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: ink, titleColor: window.themeColor('white'), bodyColor: window.themeColor('white'), padding: 10, cornerRadius: 12, displayColors: false,
                                        callbacks: { label: (c) => 'Rp ' + Number(c.parsed.y).toLocaleString('id-ID') }
                                    }
                                },
                                scales: {
                                    x: { grid: { display: false }, border: { display: false }, ticks: { color: window.themeColor('zinc-400'), font: { family: 'Poppins', size: 11 } } },
                                    y: { display: false, min: 0 }
                                }
                            }
                        });
                        this.isBuilding = false;
                    });
                },
                init() {
                    this.buildChart(@js($this->salesTrendData['labels']), @js($this->salesTrendData['data']));
                    Livewire.on('sales-trend-updated', (data) => {
                        this.buildChart(data.chart.labels, data.chart.data);
                    });
                }
            }" x-on:theme-changed.window="buildChart(labels, data)">
                <canvas x-ref="canvas" id="salesChart" class="w-full h-full"></canvas>
            </div>
        </div>

        <!-- Metode pembayaran -->
        <div class="tile col-span-2 md:col-span-4 xl:col-span-4 flex flex-col">
            <h2 class="tile-title">Metode Pembayaran</h2>
            <p class="tile-sub">Uang masuk per metode (termasuk pajak)</p>

            @php
                $gradientParts = collect($this->paymentSlices)->map(fn($s) => "{$s['color']} {$s['start']}% {$s['end']}%")->implode(', ');
            @endphp
            <div class="flex flex-col sm:flex-row xl:flex-col items-center gap-5 mt-5 flex-1">
                <div class="relative w-36 h-36 flex-shrink-0">
                    <div class="absolute inset-0 rounded-full pay-color" style="background: conic-gradient({{ $gradientParts ?: '#f4f4f5 0% 100%' }});"></div>
                    <div class="absolute inset-[1.25rem] bg-white rounded-full flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold">{{ $this->nonCashPercentage }}%</span>
                        <span class="text-[9px] font-semibold text-zinc-400 tracking-widest uppercase">Non-tunai</span>
                    </div>
                </div>
                <div class="w-full space-y-2">
                    @foreach ($this->paymentSlices as $slice)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-zinc-50 px-3.5 py-2.5">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 ring-1 ring-zinc-300 pay-color" style="background-color: {{ $slice['color'] }}"></span>
                                <span class="text-sm font-medium truncate">{{ $slice['label'] }}</span>
                            </div>
                            <span class="text-sm font-semibold whitespace-nowrap">{{ $rp($slice['amount']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Produk terlaris -->
        <div class="tile col-span-2 md:col-span-4 xl:col-span-5 min-w-0">
            <div class="flex items-center justify-between mb-5">
                <h2 class="tile-title">Produk Terlaris</h2>
                <a href="/products/top?outlet={{ $selectedOutlet }}" wire:navigate class="btn-ghost btn-sm">Lihat semua <x-icon name="chevron-right" class="w-3.5 h-3.5" /></a>
            </div>
            @php $maxSold = $topSelling->max('total_sold') ?: 1; @endphp
            <div class="space-y-4">
                @forelse ($topSelling as $index => $item)
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold flex-shrink-0 {{ $index === 0 ? 'bg-ink text-white' : 'bg-zinc-100 text-zinc-600' }}">{{ $index + 1 }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline gap-2 mb-1.5">
                                <p class="text-sm font-semibold truncate">{{ $item->product_name }}</p>
                                <span class="text-xs text-zinc-500 whitespace-nowrap">{{ $item->total_sold }} terjual</span>
                            </div>
                            <div class="w-full bg-zinc-100 rounded-full h-1.5">
                                <div class="bg-ink h-1.5 rounded-full" style="width: {{ ($item->total_sold / $maxSold) * 100 }}%; opacity: {{ 1 - $index * 0.2 }}"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ui.empty icon="star" title="Belum ada penjualan" />
                @endforelse
            </div>
        </div>

        <!-- Transaksi terbaru -->
        <div class="tile col-span-2 md:col-span-4 xl:col-span-7 min-w-0">
            <div class="flex items-center justify-between mb-3">
                <h2 class="tile-title">Transaksi Terbaru</h2>
                <a href="/transactions?outlet={{ $selectedOutlet }}" wire:navigate class="btn-ghost btn-sm">Lihat semua <x-icon name="chevron-right" class="w-3.5 h-3.5" /></a>
            </div>
            <div class="divide-y divide-zinc-100">
                @forelse ($recentTransactions as $tx)
                    <div class="flex items-center gap-3 py-3">
                        <div class="w-9 h-9 rounded-xl bg-zinc-100 flex items-center justify-center flex-shrink-0"><x-icon name="receipt" class="w-4 h-4" /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold truncate">{{ $tx->order_id }}</p>
                            <p class="text-xs text-zinc-400">{{ $tx->created_at->format('d M, H:i') }} · {{ $tx->items->sum('quantity') }} item</p>
                        </div>
                        <span class="{{ \App\Models\PaymentMethod::allByCode()->get($tx->payment_method)?->is_cash ? 'badge-light' : 'badge-dark' }} hidden sm:inline-flex">{{ \App\Models\PaymentMethod::labelFor($tx->payment_method) }}</span>
                        <p class="text-sm font-bold whitespace-nowrap">{{ $rp($tx->total) }}</p>
                    </div>
                @empty
                    <x-ui.empty icon="receipt" title="Belum ada transaksi di periode ini" />
                @endforelse
            </div>
        </div>

        <!-- Stok menipis -->
        <div class="tile col-span-full">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="tile-title">Stok Menipis</h2>
                    <p class="tile-sub">{{ $lowStockOutlet }} · stok ≤ {{ \App\Models\Setting::lowStockThreshold() }}</p>
                </div>
                <a href="/products?lowStock=1" wire:navigate class="btn-ghost btn-sm">Lihat semua <x-icon name="chevron-right" class="w-3.5 h-3.5" /></a>
            </div>
            @if ($lowStock->isEmpty())
                <x-ui.empty icon="check-circle" title="Semua stok aman" />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                    @foreach ($lowStock as $row)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-zinc-50 px-3.5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate">{{ $row->product_name }}</p>
                                @if ($row->product_variant_id)
                                    <p class="text-xs text-zinc-500">{{ $row->size }} - {{ $row->color }}</p>
                                @endif
                            </div>
                            <span class="{{ $row->quantity <= 0 ? 'badge-danger' : 'badge-warning' }}">{{ $row->quantity <= 0 ? 'Habis' : $row->quantity }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
