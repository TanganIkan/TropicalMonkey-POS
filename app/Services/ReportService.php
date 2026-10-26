<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query laporan penjualan untuk dashboard owner, halaman Laporan, dan export.
 * $outletId null/'' berarti semua outlet. Transaksi void tidak pernah dihitung.
 *
 * Pendapatan (net sales) = subtotal - diskon total belanja, tanpa pajak.
 */
class ReportService
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: string} [start, end, 'day'|'month'|'year']
     */
    public function range(string $filter, $customStart = null, $customEnd = null): array
    {
        return match ($filter) {
            'monthly' => [now()->subMonths(11)->startOfMonth(), now()->endOfMonth(), 'month'],
            'yearly' => [now()->subYears(4)->startOfYear(), now()->endOfYear(), 'year'],
            'custom' => [Carbon::parse($customStart)->startOfDay(), Carbon::parse($customEnd)->endOfDay(), 'day'],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'day'],
        };
    }

    public function transactions(Carbon $start, Carbon $end, $outletId = null): Builder
    {
        return Transaction::query()
            ->completed()
            ->whereBetween('created_at', [$start, $end])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId));
    }

    protected function items(Carbon $start, Carbon $end, $outletId = null): Builder
    {
        return TransactionItem::query()
            ->whereIn('transaction_id', $this->transactions($start, $end, $outletId)->select('id'));
    }

    public function salesTrend(Carbon $start, Carbon $end, string $groupType, $outletId = null): array
    {
        $format = match ($groupType) {
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
        };

        // MySQL: DATE_FORMAT, SQLite (test): strftime — format tanggalnya sama
        $period = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', created_at)"
            : "DATE_FORMAT(created_at, '{$format}')";

        $rows = $this->transactions($start, $end, $outletId)
            ->select(DB::raw("{$period} as period_key"), DB::raw('SUM(subtotal - discount_amount) as total'))
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

    /**
     * Uang masuk per metode bayar (termasuk pajak), untuk grafik donat.
     */
    public function paymentSlices(Carbon $start, Carbon $end, $outletId = null): array
    {
        $payments = TransactionPayment::query()
            ->whereIn('transaction_id', $this->transactions($start, $end, $outletId)->select('id'))
            ->select('payment_method', DB::raw('SUM(amount + tax_amount) as total_amount'))
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method');

        $total = $payments->sum();
        $slices = [];
        $cumulative = 0;

        foreach (PaymentMethod::allByCode() as $code => $method) {
            $amount = (float) ($payments[$code] ?? 0);

            // Metode nonaktif hanya ditampilkan bila masih ada transaksinya
            if (!$method->is_active && $amount == 0) {
                continue;
            }

            $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
            $slices[] = [
                'key' => $code,
                'label' => $method->name,
                'color' => $method->color,
                'is_cash' => $method->is_cash,
                'amount' => $amount,
                'percentage' => $percentage,
                'start' => $cumulative,
                'end' => $cumulative + $percentage,
            ];
            $cumulative += $percentage;
        }

        return $slices;
    }

    public function nonCashPercentage(array $slices): int
    {
        $slices = collect($slices);
        $total = $slices->sum('amount');
        $nonCash = $slices->where('is_cash', false)->sum('amount');

        return $total > 0 ? round(($nonCash / $total) * 100) : 0;
    }

    /**
     * Angka ringkasan untuk kartu dashboard dan export.
     */
    public function summary(Carbon $start, Carbon $end, $outletId = null): array
    {
        $transactions = $this->transactions($start, $end, $outletId);

        return [
            'totalTransactions' => (clone $transactions)->count(),
            // Pendapatan = penjualan setelah diskon, tanpa pajak. Pajak dihitung terpisah
            // (taxNonLokal), sehingga angka ini sama dengan total laporan per brand.
            'totalRevenue' => (float) (clone $transactions)->sum(DB::raw('subtotal - discount_amount')),
            'totalItemsSold' => $this->items($start, $end, $outletId)->sum('quantity'),
            'totalDiscount' => (float) (clone $transactions)->sum('discount_amount') + (float) $this->items($start, $end, $outletId)->sum('discount_amount'),
            'taxNonLokal' => (float) (clone $transactions)->sum('tax_amount'),
            'topProduct' => $this->bestSellers($this->items($start, $end, $outletId))->first(),
            'topSelling' => $this->bestSellers($this->items($start, $end, $outletId))
                ->with('product.brand')
                ->limit(4)
                ->get(),
        ];
    }

    /**
     * Item terjual dijumlahkan per produk (semua varian digabung) dengan nama
     * produk terbaru, diurutkan dari yang paling laku.
     */
    protected function bestSellers(Builder $items): Builder
    {
        return $items
            ->join('products', 'products.id', '=', 'transaction_items.product_id')
            ->select('transaction_items.product_id', 'products.name as product_name', DB::raw('SUM(transaction_items.quantity) as total_sold'))
            ->groupBy('transaction_items.product_id', 'products.name')
            ->orderByDesc('total_sold');
    }

    /**
     * Daftar produk terlaris (halaman Produk Terlaris). Tanpa tanggal = sepanjang waktu.
     */
    public function topProducts($outletId = null, int $perPage = 15, ?Carbon $start = null, ?Carbon $end = null)
    {
        $transactions = Transaction::query()->completed()
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->when($start, fn($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn($q) => $q->where('created_at', '<=', $end));

        $items = TransactionItem::query()->whereIn('transaction_id', $transactions->select('id'));

        return $this->bestSellers($items)->paginate($perPage);
    }

    /**
     * Seluruh data untuk export laporan penjualan (PDF).
     */
    public function salesReport(Carbon $start, Carbon $end, $outletId = null): array
    {
        return $this->summary($start, $end, $outletId) + [
            'outletName' => $this->outletName($outletId),
            'periodRange' => $this->periodLabel($start, $end),
            'paymentSlices' => $this->paymentSlices($start, $end, $outletId),
            'allTransactions' => $this->transactions($start, $end, $outletId)
                ->with(['items.product.brand', 'items.variant', 'payments'])
                ->orderBy('created_at', 'desc')
                ->get(),
        ];
    }

    /**
     * Item terjual dikelompokkan per brand, diurutkan sesuai abjad.
     */
    public function brandRevenue(Carbon $start, Carbon $end, $outletId = null): array
    {
        $items = $this->items($start, $end, $outletId)->with(['product.brand', 'transaction'])->get();
        $brandsData = [];

        foreach ($items as $item) {
            $brandName = $item->product?->brand?->name ?? 'Tanpa Brand';

            $brandsData[$brandName] ??= [
                'brand_name' => $brandName,
                'items' => [],
                'total_revenue' => 0,
                'total_qty' => 0,
            ];

            $brandsData[$brandName]['items'][] = $item;
            // Setelah diskon per item & bagian diskon total belanja
            $brandsData[$brandName]['total_revenue'] += $item->netAmount();
            $brandsData[$brandName]['total_qty'] += $item->quantity;
        }

        ksort($brandsData);

        return $brandsData;
    }

    /**
     * Laba kotor = pendapatan - HPP (HPP disimpan per item saat transaksi).
     */
    public function profit(Carbon $start, Carbon $end, $outletId = null): array
    {
        $rows = $this->items($start, $end, $outletId)
            ->leftJoin('products', 'products.id', '=', 'transaction_items.product_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->select(
                DB::raw("COALESCE(brands.name, 'Tanpa Brand') as brand_name"),
                DB::raw('SUM(transaction_items.quantity) as qty'),
                DB::raw('SUM(transaction_items.subtotal - transaction_items.order_discount_share) as revenue'),
                DB::raw('SUM(transaction_items.cost_price * transaction_items.quantity) as cogs'),
            )
            ->groupBy('brand_name')
            ->orderBy('brand_name')
            ->get()
            ->map(fn($r) => [
                'brand_name' => $r->brand_name,
                'qty' => (int) $r->qty,
                'revenue' => (float) $r->revenue,
                'cogs' => (float) $r->cogs,
                'profit' => (float) $r->revenue - (float) $r->cogs,
                'margin' => $r->revenue > 0 ? ((float) $r->revenue - (float) $r->cogs) / (float) $r->revenue * 100 : 0,
            ]);

        $revenue = $rows->sum('revenue');
        $cogs = $rows->sum('cogs');

        return [
            'by_brand' => $rows->all(),
            'revenue' => $revenue,
            'cogs' => $cogs,
            'profit' => $revenue - $cogs,
            'margin' => $revenue > 0 ? ($revenue - $cogs) / $revenue * 100 : 0,
        ];
    }

    /**
     * Kinerja per kasir: jumlah transaksi, penjualan, diskon yang diberikan, void.
     */
    public function cashiers(Carbon $start, Carbon $end, $outletId = null): array
    {
        $completed = $this->transactions($start, $end, $outletId)
            ->select(
                'user_id',
                DB::raw('COUNT(*) as transactions'),
                DB::raw('SUM(subtotal - discount_amount) as net_sales'),
                DB::raw('SUM(discount_amount) as order_discount'),
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $itemDiscounts = $this->items($start, $end, $outletId)
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->select('transactions.user_id', DB::raw('SUM(transaction_items.discount_amount) as discount'))
            ->groupBy('transactions.user_id')
            ->pluck('discount', 'user_id');

        $voids = Transaction::where('status', Transaction::STATUS_VOID)
            ->whereBetween('created_at', [$start, $end])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->select('user_id', DB::raw('COUNT(*) as c'))
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        $userIds = $completed->keys()->merge($voids->keys())->unique();
        $users = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');

        return $userIds->map(function ($userId) use ($completed, $itemDiscounts, $voids, $users) {
            $row = $completed->get($userId);
            $count = (int) ($row->transactions ?? 0);
            $sales = (float) ($row->net_sales ?? 0);

            return [
                'user' => $users->get($userId),
                'transactions' => $count,
                'net_sales' => $sales,
                'average' => $count > 0 ? $sales / $count : 0,
                'discount' => (float) ($row->order_discount ?? 0) + (float) ($itemDiscounts[$userId] ?? 0),
                'voids' => (int) ($voids[$userId] ?? 0),
            ];
        })->sortByDesc('net_sales')->values()->all();
    }

    /**
     * Nilai persediaan per outlet (stok x HPP dan stok x harga jual).
     * Varian nonaktif tidak dihitung.
     */
    public function inventoryValue($outletId = null): array
    {
        $rows = $this->activeStocks()
            ->when($outletId, fn($q) => $q->where('stocks.outlet_id', $outletId))
            ->join('outlets', 'outlets.id', '=', 'stocks.outlet_id')
            ->select(
                'outlets.name as outlet_name',
                DB::raw('SUM(stocks.quantity) as qty'),
                DB::raw('SUM(stocks.quantity * COALESCE(product_variants.cost_price, products.cost_price)) as cost_value'),
                DB::raw('SUM(stocks.quantity * COALESCE(product_variants.sell_price, products.sell_price)) as sell_value'),
            )
            ->where('stocks.quantity', '>', 0)
            ->groupBy('outlets.name')
            ->orderBy('outlets.name')
            ->get()
            ->map(fn($r) => [
                'outlet_name' => $r->outlet_name,
                'qty' => (int) $r->qty,
                'cost_value' => (float) $r->cost_value,
                'sell_value' => (float) $r->sell_value,
            ]);

        return [
            'by_outlet' => $rows->all(),
            'qty' => $rows->sum('qty'),
            'cost_value' => $rows->sum('cost_value'),
            'sell_value' => $rows->sum('sell_value'),
        ];
    }

    /**
     * Posisi stok per barang per outlet. Dengan $asOf di masa lalu, stok dihitung
     * mundur dari stok sekarang memakai riwayat mutasi setelah tanggal tersebut
     * (mis. export sisa stok akhir bulan yang baru dibuat beberapa hari kemudian).
     */
    public function stockSnapshot(?Carbon $asOf = null, $outletId = null): Collection
    {
        $rows = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->when($outletId, fn($q) => $q->where('stocks.outlet_id', $outletId))
            // Produk bervarian hanya dihitung lewat baris variannya
            ->where(fn($q) => $q->whereNotNull('stocks.product_variant_id')->orWhere('products.has_variants', false))
            ->select(
                'stocks.product_id',
                'stocks.product_variant_id',
                'stocks.outlet_id',
                'stocks.quantity',
                'products.name as product_name',
                'categories.name as category_name',
                'product_variants.size',
                'product_variants.color',
                DB::raw("COALESCE(brands.name, 'Tanpa Brand') as brand_name"),
                DB::raw('COALESCE(product_variants.sku, products.sku) as sku'),
                DB::raw('COALESCE(product_variants.barcode, products.barcode) as barcode'),
                DB::raw('COALESCE(product_variants.cost_price, products.cost_price) as cost_price'),
                DB::raw('COALESCE(product_variants.sell_price, products.sell_price) as sell_price'),
            )
            ->get();

        if ($asOf && $asOf->lt(now())) {
            $incoming = StockHistory::typesFor('in');
            $placeholders = implode(',', array_fill(0, count($incoming), '?'));

            $after = StockHistory::query()
                ->where('created_at', '>', $asOf)
                ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
                ->selectRaw("product_id, product_variant_id, outlet_id, SUM(CASE WHEN type IN ({$placeholders}) THEN quantity ELSE -quantity END) as net", $incoming)
                ->groupBy('product_id', 'product_variant_id', 'outlet_id')
                ->get()
                ->keyBy(fn($h) => "{$h->product_id}-{$h->product_variant_id}-{$h->outlet_id}");

            foreach ($rows as $row) {
                $row->quantity -= (int) ($after["{$row->product_id}-{$row->product_variant_id}-{$row->outlet_id}"]->net ?? 0);
            }
        }

        return $rows;
    }

    /**
     * Sisa stok dikelompokkan per brand -> per barang, dengan stok per outlet.
     *
     * @return array<string, array{brand_name: string, items: list<array>, qty: int, cost_value: float, sell_value: float}>
     */
    public function stockByBrand(Collection $snapshot, bool $includeZero = false): array
    {
        $brands = [];

        foreach ($snapshot->groupBy(fn($r) => $r->product_id . '-' . ($r->product_variant_id ?? 'single')) as $rows) {
            $first = $rows->first();
            $qty = (int) $rows->sum('quantity');

            if ($qty === 0 && !$includeZero) {
                continue;
            }

            $brand = $first->brand_name;
            $brands[$brand] ??= ['brand_name' => $brand, 'items' => [], 'qty' => 0, 'cost_value' => 0, 'sell_value' => 0];

            $brands[$brand]['items'][] = [
                'product_name' => $first->product_name,
                'variant' => $first->product_variant_id ? "{$first->size} - {$first->color}" : null,
                'category' => $first->category_name,
                'sku' => $first->sku,
                'barcode' => $first->barcode,
                'outlets' => $rows->mapWithKeys(fn($r) => [$r->outlet_id => (int) $r->quantity])->all(),
                'qty' => $qty,
                'cost_price' => (float) $first->cost_price,
                'sell_price' => (float) $first->sell_price,
                'cost_value' => $qty * (float) $first->cost_price,
                'sell_value' => $qty * (float) $first->sell_price,
            ];
            $brands[$brand]['qty'] += $qty;
            $brands[$brand]['cost_value'] += $qty * (float) $first->cost_price;
            $brands[$brand]['sell_value'] += $qty * (float) $first->sell_price;
        }

        ksort($brands);
        foreach ($brands as &$brand) {
            usort($brand['items'], fn($a, $b) => [$a['product_name'], $a['variant']] <=> [$b['product_name'], $b['variant']]);
        }

        return $brands;
    }

    /**
     * Barang aktif yang stoknya di bawah/sama dengan batas.
     */
    public function lowStock(int $outletId, int $threshold, ?int $limit = null)
    {
        return $this->activeStocks()
            ->where('stocks.outlet_id', $outletId)
            ->where('stocks.quantity', '<=', $threshold)
            ->where('products.is_active', true)
            // Produk bervarian hanya dihitung lewat baris variannya
            ->where(fn($q) => $q->whereNotNull('stocks.product_variant_id')->orWhere('products.has_variants', false))
            ->select(
                'stocks.product_id',
                'stocks.product_variant_id',
                'stocks.quantity',
                'products.name as product_name',
                'product_variants.size',
                'product_variants.color',
            )
            ->orderBy('stocks.quantity')
            ->orderBy('products.name')
            ->when($limit, fn($q) => $q->limit($limit))
            ->get();
    }

    protected function activeStocks()
    {
        return Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->where(fn($q) => $q->whereNull('stocks.product_variant_id')->orWhere('product_variants.is_active', true));
    }

    public function outletName($outletId = null): string
    {
        return $outletId ? (Outlet::find($outletId)?->name ?? 'Semua Outlet') : 'Semua Outlet';
    }

    public function periodLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('d M Y') . ' - ' . $end->format('d M Y');
    }
}
