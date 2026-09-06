<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

    protected function outletFilter($query)
    {
        return $query->when($this->selectedOutlet !== '', fn($q) => $q->where('outlet_id', $this->selectedOutlet));
    }

    protected function getTrendRange()
    {
        return match ($this->salesFilter) {
            'daily' => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'day'],
            'monthly' => [now()->subMonths(11)->startOfMonth(), now()->endOfMonth(), 'month'],
            'yearly' => [now()->subYears(4)->startOfYear(), now()->endOfYear(), 'year'],
            'custom' => [\Carbon\Carbon::parse($this->customStart)->startOfDay(), \Carbon\Carbon::parse($this->customEnd)->endOfDay(), 'day'],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'day'],
        };
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
                'customEnd.after_or_equal' => 'Tanggal akhir tidak boleh lebih kecil dari tanggal mulai.',
            ]
        );

        $this->salesFilter = 'custom';
        $this->dispatchChartData();
    }

    public function getSalesTrendDataProperty()
    {
        [$start, $end, $groupType] = $this->getTrendRange();
        $format = match ($groupType) {
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
        };

        $rows = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw("DATE_FORMAT(created_at, '{$format}') as period_key"), DB::raw('SUM(total) as total'))
            ->groupBy('period_key')
            ->pluck('total', 'period_key');

        $labels = [];
        $data = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $key = match ($groupType) {
                'day' => $cursor->format('Y-m-d'),
                'month' => $cursor->format('Y-m'),
                'year' => $cursor->format('Y'),
            };
            $labels[] = match ($groupType) {
                'day' => $cursor->translatedFormat('d M'),
                'month' => $cursor->translatedFormat('M Y'),
                'year' => $cursor->format('Y'),
            };
            $data[] = (float) ($rows[$key] ?? 0);
            $cursor = match ($groupType) {
                'day' => $cursor->addDay(),
                'month' => $cursor->addMonth(),
                'year' => $cursor->addYear(),
            };
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public function getPaymentSlicesProperty()
    {
        [$start, $end] = $this->getTrendRange();

        $payments = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->select('payment_method', DB::raw('SUM(total) as total_amount'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $total = $payments->sum('total_amount');

        $methods = [
            'qris' => ['label' => 'QRIS', 'color' => '#111827'],
            'kartu_lokal' => ['label' => 'Kartu Lokal', 'color' => '#4b5563'],
            'kartu_non_lokal' => ['label' => 'Kartu Non-Lokal', 'color' => '#9ca3af'],
            'tunai' => ['label' => 'Tunai', 'color' => '#e5e7eb'],
        ];

        $slices = [];
        $cumulative = 0;

        foreach ($methods as $key => $meta) {
            $amount = $payments[$key]->total_amount ?? 0;
            $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
            $slices[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'amount' => $amount,
                'percentage' => $percentage,
                'start' => $cumulative,
                'end' => $cumulative + $percentage,
            ];
            $cumulative += $percentage;
        }

        return $slices;
    }

    public function getNonCashPercentageProperty()
    {
        $slices = collect($this->paymentSlices);
        $total = $slices->sum('amount');
        $nonCash = $slices->where('key', '!=', 'tunai')->sum('amount');

        return $total > 0 ? round(($nonCash / $total) * 100) : 0;
    }

    public function with(): array
    {
        // Semua data kini ditarik HANYA berdasarkan rentang filter
        [$start, $end] = $this->getTrendRange();

        $trxIds = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->pluck('id');

        $totalTransactions = $trxIds->count();

        $totalRevenue = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');

        $totalItemsSold = TransactionItem::whereIn('transaction_id', $trxIds)->sum('quantity');

        $topProduct = TransactionItem::select('product_name', DB::raw('SUM(quantity) as total_sold'))
            ->whereIn('transaction_id', $trxIds)
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->first();

        $topSelling = TransactionItem::with('product.brand')
            ->select('product_name', 'product_id', DB::raw('SUM(quantity) as total_sold'))
            ->whereIn('transaction_id', $trxIds)
            ->groupBy('product_name', 'product_id')
            ->orderByDesc('total_sold')
            ->limit(4)
            ->get();

        $recentTransactions = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->with('items')->latest()->limit(4)->get();

        $taxNonLokal = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_method', 'kartu_non_lokal')
            ->sum('tax_amount');

        return compact('totalRevenue', 'totalTransactions', 'totalItemsSold', 'topProduct', 'topSelling', 'recentTransactions', 'taxNonLokal');
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
        $outletName = $this->selectedOutlet ? \App\Models\Outlet::find($this->selectedOutlet)->name : 'Semua Outlet';

        $trxIds = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->pluck('id');

        $totalTransactions = $trxIds->count();

        $totalRevenue = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');

        $totalItemsSold = TransactionItem::whereIn('transaction_id', $trxIds)->sum('quantity');

        $taxNonLokal = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_method', 'kartu_non_lokal')
            ->sum('tax_amount');

        $topProduct = TransactionItem::select('product_name', DB::raw('SUM(quantity) as total_sold'))
            ->whereIn('transaction_id', $trxIds)
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->first();

        $topSelling = TransactionItem::select('product_name', 'product_id', DB::raw('SUM(quantity) as total_sold'))
            ->whereIn('transaction_id', $trxIds)
            ->groupBy('product_name', 'product_id')
            ->orderByDesc('total_sold')
            ->limit(4)
            ->get();

        $allTransactions = $this->outletFilter(Transaction::query())
            ->whereBetween('created_at', [$start, $end])
            ->with(['items.product.brand', 'items.variant'])
            ->orderBy('created_at', 'desc')
            ->get();

        $slices = collect($this->paymentSlices);
        $paymentBreakdown = [
            'cash' => $slices->firstWhere('key', 'tunai')['amount'] ?? 0,
            'kartu_lokal' => $slices->firstWhere('key', 'kartu_lokal')['amount'] ?? 0,
            'kartu_non_lokal' => $slices->firstWhere('key', 'kartu_non_lokal')['amount'] ?? 0,
            'qris' => $slices->firstWhere('key', 'qris')['amount'] ?? 0,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.dashboard-report', [
            'outletName' => $outletName,
            'totalRevenue' => $totalRevenue,
            'totalTransactions' => $totalTransactions,
            'totalItemsSold' => $totalItemsSold,
            'topProduct' => $topProduct,
            'paymentBreakdown' => $paymentBreakdown,
            'topSelling' => $topSelling,
            'allTransactions' => $allTransactions,
            'periodRange' => $start->format('d M Y') . ' - ' . $end->format('d M Y'),
            'taxNonLokal' => $taxNonLokal,
        ]);

        return response()->streamDownload(fn() => print $pdf->output(), 'laporan-penjualan-' . now()->format('Ymd-His') . '.pdf');
    }
};
?>

<div class="p-4 md:p-6 lg:p-8 bg-gray-50/50 min-h-screen w-full overflow-hidden">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 md:mb-8 gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Dashboard Owner</h1>
            <p class="text-sm text-gray-500 mt-1">Ringkasan performa toko berdasarkan periode terpilih.</p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
            <select wire:model.live="selectedOutlet"
                class="w-full sm:w-auto px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white shadow-sm outline-none focus:ring-2 focus:ring-primary">
                <option value="">Semua Outlet</option>
                @foreach (\App\Models\Outlet::where('is_active', true)->get() as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>
            <button wire:click="exportPdf" wire:loading.attr="disabled"
                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-primary/90 transition">
                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span wire:loading.remove wire:target="exportPdf">Ekspor Laporan</span>
                <span wire:loading wire:target="exportPdf">Menyiapkan...</span>
            </button>
        </div>
    </div>

    <!-- 5 Top Cards (Metrics Terfilter) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 md:gap-6 mb-6">

        <!-- Kotak 1: Total Pendapatan -->
        <div class="bg-white rounded-2xl p-4 md:p-6 border border-gray-100 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-primary text-white flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                </div>
            </div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider line-clamp-1">Total Pendapatan</p>
            <h3 class="text-xl md:text-2xl font-bold text-gray-900 mt-1 truncate"
                title="Rp {{ number_format($totalRevenue, 0, ',', '.') }}">
                Rp {{ number_format($totalRevenue, 0, ',', '.') }}
            </h3>
        </div>

        <!-- Kotak 2: Total Transaksi -->
        <div class="bg-white rounded-2xl p-4 md:p-6 border border-gray-100 shadow-sm">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-primary text-white flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                </div>
            </div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider line-clamp-1">Total Transaksi</p>
            <h3 class="text-xl md:text-2xl font-bold text-gray-900 mt-1">
                {{ number_format($totalTransactions, 0, ',', '.') }}
            </h3>
        </div>

        <!-- Kotak 3: Item Terjual -->
        <div class="bg-white rounded-2xl p-4 md:p-6 border border-gray-100 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-primary text-white flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider line-clamp-1">Item Terjual</p>
            <h3 class="text-xl md:text-2xl font-bold text-gray-900 mt-1">
                {{ number_format($totalItemsSold, 0, ',', '.') }} <span
                    class="text-sm font-medium text-gray-500">unit</span>
            </h3>
        </div>

        <!-- Kotak 4: Produk Teratas -->
        <div
            class="bg-white rounded-2xl p-4 md:p-6 border border-gray-100 shadow-sm sm:col-span-2 md:col-span-1 xl:col-span-1">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center text-white flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                        </path>
                    </svg>
                </div>
                <span
                    class="inline-flex items-center text-[10px] font-bold text-primary bg-gray-100 border border-gray-200 px-2 py-1 rounded uppercase tracking-wider">Terlaris</span>
            </div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider line-clamp-1">Produk Teratas</p>
            <h3 class="text-lg font-bold text-gray-900 mt-1 truncate" title="{{ $topProduct->product_name ?? 'N/A' }}">
                {{ $topProduct->product_name ?? 'Belum ada data' }}
            </h3>
        </div>

        <!-- Kotak 5: Pajak -->
        <div
            class="bg-white rounded-2xl p-4 md:p-6 border border-gray-100 shadow-sm relative overflow-hidden sm:col-span-2 md:col-span-2 xl:col-span-1">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center text-white flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z">
                        </path>
                    </svg>
                </div>
                <span
                    class="inline-flex items-center text-[10px] font-bold text-primary bg-gray-100 border border-gray-200 px-2 py-1 rounded uppercase tracking-wider">Tax</span>
            </div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider line-clamp-1">Pajak (Kartu Luar)</p>
            <h3 class="text-xl md:text-2xl font-bold text-gray-900 mt-1 truncate"
                title="Rp {{ number_format($taxNonLokal ?? 0, 0, ',', '.') }}">
                Rp {{ number_format($taxNonLokal ?? 0, 0, ',', '.') }}
            </h3>
        </div>
    </div>

    <!-- Chart & Pembayaran Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6">

        <!-- Sales Trend Chart -->
        <div
            class="bg-white rounded-3xl p-5 md:p-6 border border-gray-100 shadow-sm lg:col-span-2 flex flex-col min-w-0">
            <!-- PERBAIKAN: Filter header dibuat flex-wrap agar tidak tabrakan di layar tablet yang nanggung -->
            <div class="flex flex-col xl:flex-row xl:items-start justify-between mb-6 gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Tren Penjualan</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Performa pendapatan berdasarkan periode</p>
                </div>

                <!-- Di sini kuncinya: items-end, justify-end, dan ml-auto -->
                <div
                    class="flex flex-col xl:flex-row items-end xl:items-center justify-end gap-3 w-full xl:w-auto ml-auto">
                    <!-- Tab Buttons -->
                    <div
                        class="flex items-center bg-gray-50 rounded-lg p-1 border border-gray-200 w-full sm:w-auto flex-shrink-0">
                        <button wire:click="setFilter('daily')"
                            class="flex-1 sm:flex-none text-center px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $salesFilter === 'daily' ? 'bg-white shadow-sm text-primary' : 'text-gray-500 hover:text-gray-900' }}">7
                            Hari</button>
                        <button wire:click="setFilter('monthly')"
                            class="flex-1 sm:flex-none text-center px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $salesFilter === 'monthly' ? 'bg-white shadow-sm text-primary' : 'text-gray-500 hover:text-gray-900' }}">12
                            Bulan</button>
                        <button wire:click="setFilter('yearly')"
                            class="flex-1 sm:flex-none text-center px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $salesFilter === 'yearly' ? 'bg-white shadow-sm text-primary' : 'text-gray-500 hover:text-gray-900' }}">5
                            Tahun</button>
                    </div>

                    <!-- Date Picker Custom -->
                    <div
                        class="flex items-center gap-2 bg-gray-50 rounded-lg p-1 border border-gray-200 w-full sm:w-auto">
                        <div
                            class="flex items-center justify-between flex-1 gap-1 bg-white sm:bg-transparent border border-gray-200 sm:border-0 rounded-md p-1.5 sm:p-0">
                            <input type="date" wire:model="customStart"
                                class="w-full sm:w-[110px] text-xs border-0 bg-transparent px-1 outline-none {{ $salesFilter === 'custom' ? 'text-primary font-semibold' : 'text-gray-500' }}">
                            <span class="text-gray-300 text-xs font-bold px-1">-</span>
                            <input type="date" wire:model="customEnd"
                                class="w-full sm:w-[110px] text-xs border-0 bg-transparent px-1 outline-none {{ $salesFilter === 'custom' ? 'text-primary font-semibold' : 'text-gray-500' }}">
                        </div>
                        <button wire:click="applyCustomRange"
                            class="px-4 py-1.5 text-xs font-semibold rounded-md bg-primary text-white hover:bg-primary/90 transition shadow-sm whitespace-nowrap">Terapkan</button>
                    </div>
                </div>
            </div>

            @error('customStart') <span class="text-red-500 text-xs block mb-3 -mt-3">{{ $message }}</span> @enderror
            @error('customEnd') <span class="text-red-500 text-xs block mb-3 -mt-3">{{ $message }}</span> @enderror

            <!-- PERBAIKAN: Padding ditambahkan di setting layout Chart JS -->
            <div class="relative w-full h-[250px] md:h-[300px] flex items-end mt-2" wire:ignore x-data="{
                chart: null,
                isBuilding: false,
                buildChart(labels, data) {
                    if (this.isBuilding) return;
                    this.isBuilding = true;
                    if (this.chart) { this.chart.destroy(); this.chart = null; }
                    this.$nextTick(() => {
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Pendapatan Penjualan',
                                    data: data,
                                    borderColor: '#111827',
                                    backgroundColor: 'rgba(17, 24, 39, 0.05)',
                                    borderWidth: 3,
                                    tension: 0.4,
                                    fill: true,
                                    pointBackgroundColor: '#ffffff',
                                    pointBorderColor: '#111827',
                                    pointBorderWidth: 2,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false,
                                layout: { padding: { left: 15, right: 15, top: 15, bottom: 0 } },
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { grid: { display: false }, border: { display: false } },
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
            }">
                <canvas x-ref="canvas" id="salesChart" class="w-full h-full"></canvas>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="bg-white rounded-3xl p-5 md:p-6 border border-gray-100 shadow-sm flex flex-col">
            <h2 class="text-lg font-bold text-gray-900">Metode Pembayaran</h2>
            <p class="text-xs text-gray-500 mt-0.5">Rincian per metode di periode ini</p>

            <div class="flex-1 flex flex-col items-center justify-center py-6">
                @php
                    $gradientParts = collect($this->paymentSlices)
                        ->map(fn($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
                        ->implode(', ');
                @endphp
                <div class="relative w-36 h-36 rounded-full flex items-center justify-center shadow-inner"
                    style="background: conic-gradient({{ $gradientParts ?: '#f3f4f6 0% 100%' }});">
                    <div class="w-28 h-28 bg-white rounded-full flex flex-col items-center justify-center shadow-sm">
                        <span class="text-3xl font-extrabold text-gray-900">{{ $this->nonCashPercentage }}%</span>
                        <span
                            class="text-[9px] font-bold text-gray-500 tracking-widest mt-0.5 uppercase">Non-Tunai</span>
                    </div>
                </div>
            </div>

            <div class="space-y-2.5 mt-2">
                @foreach ($this->paymentSlices as $slice)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $slice['color'] }}">
                            </div>
                            <span class="text-sm font-semibold text-gray-800">{{ $slice['label'] }}</span>
                        </div>
                        <span class="text-sm font-bold text-gray-900">Rp
                            {{ number_format($slice['amount'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Bottom Data Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
        <!-- Top Selling Products -->
        <div class="bg-white rounded-3xl p-5 md:p-6 border border-gray-100 shadow-sm min-w-0">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-900">Produk Terlaris</h2>
                <a href="/products/top?outlet={{ $selectedOutlet }}" wire:navigate
                    class="text-sm font-semibold text-primary hover:text-primary/80 transition">Lihat Semua</a>
            </div>

            <div class="space-y-5">
                @php
                    $colors = ['bg-gray-900', 'bg-gray-700', 'bg-gray-500', 'bg-gray-300'];
                    $maxSold = $topSelling->max('total_sold') ?? 1;
                @endphp

                @forelse($topSelling as $index => $item)
                    @php
                        $percentage = ($item->total_sold / $maxSold) * 100;
                        $barColor = $colors[$index % 4];
                    @endphp
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 rounded-xl bg-gray-100 border border-gray-200 flex-shrink-0 flex items-center justify-center overflow-hidden">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-end mb-1.5">
                                <h4 class="text-sm font-bold text-gray-900 truncate pr-2">{{ $item->product_name }}</h4>
                                <span class="text-xs font-medium text-gray-500 flex-shrink-0">{{ $item->total_sold }}
                                    terjual</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                <div class="{{ $barColor }} h-1.5 rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">Belum ada data penjualan.</p>
                @endforelse
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-3xl p-5 md:p-6 border border-gray-100 shadow-sm min-w-0 overflow-hidden">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-bold text-gray-900">Transaksi Terbaru</h2>
                <a href="/transactions?outlet={{ $selectedOutlet }}" wire:navigate
                    class="text-sm font-semibold text-primary hover:text-primary/80 transition">Lihat Semua</a>
            </div>

            <div class="overflow-x-auto -mx-2 px-2">
                <table class="w-full text-left text-sm whitespace-nowrap min-w-[400px]">
                    <thead class="text-xs text-gray-500 font-bold uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="pb-3 px-2">Id Transaksi</th>
                            <th class="pb-3 px-2">Item</th>
                            <th class="pb-3 px-2">Metode</th>
                            <th class="pb-3 px-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentTransactions as $tx)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-4 px-2 text-gray-500 truncate max-w-[120px]">{{ $tx->order_id }}</td>
                                <td class="py-4 px-2 text-gray-500">{{ $tx->items->sum('quantity') }} item</td>
                                <td class="py-4 px-2">
                                    @php
                                        $methodLabel = match ($tx->payment_method) {
                                            'qris' => 'QRIS',
                                            'kartu_lokal', 'kartu_non_lokal' => 'KARTU',
                                            default => 'TUNAI',
                                        };
                                        $methodClass = $methodLabel === 'TUNAI' ? 'bg-gray-200 text-gray-800' : 'bg-primary text-white';
                                    @endphp
                                    <span
                                        class="px-2.5 py-1 rounded-md text-[10px] font-extrabold tracking-widest {{ $methodClass }}">{{ $methodLabel }}</span>
                                </td>
                                <td class="py-4 px-2 text-right font-bold text-gray-900">Rp
                                    {{ number_format($tx->total, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-500">Belum ada transaksi di periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>