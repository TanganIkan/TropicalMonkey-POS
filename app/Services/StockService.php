<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PosException;
use App\Models\Outlet;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu untuk mengubah stok. Setiap perubahan selalu
 * mengupdate tabel `stocks` dan mencatat `stock_histories` (jenis mutasi,
 * siapa yang melakukan, stok sebelum & sesudah).
 */
class StockService
{
    public function available(int $productId, ?int $variantId, int $outletId): int
    {
        return (int) Stock::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('outlet_id', $outletId)
            ->value('quantity');
    }

    /**
     * Stok masuk (stok awal, restock, transfer masuk, void, penyesuaian +).
     * Wajib di dalam DB::transaction agar lock-nya berlaku.
     */
    public function increase(int $productId, ?int $variantId, int $outletId, int $quantity, ?string $notaNumber = null, string $type = 'restock', ?string $note = null): Stock
    {
        $stock = $this->lockedStock($productId, $variantId, $outletId);

        if ($quantity > 0) {
            $before = $stock->quantity;
            $stock->increment('quantity', $quantity);
            $this->record($stock, $quantity, $before, $type, $notaNumber, $note);
        }

        return $stock;
    }

    /**
     * Stok keluar (penjualan, retur, transfer keluar, penyesuaian -). Baris stok
     * dikunci agar dua kasir tidak bisa menjual stok terakhir yang sama.
     * Wajib dipanggil di dalam DB::transaction supaya lock-nya berlaku.
     *
     * @throws InsufficientStockException
     */
    public function decrease(int $productId, ?int $variantId, int $outletId, int $quantity, ?string $notaNumber, string $itemName, string $type = 'sale', ?string $note = null): Stock
    {
        $stock = Stock::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('outlet_id', $outletId)
            ->lockForUpdate()
            ->first();

        $available = $stock?->quantity ?? 0;

        if (!$stock || $available < $quantity) {
            throw new InsufficientStockException($itemName, $available, $quantity);
        }

        $stock->decrement('quantity', $quantity);
        $this->record($stock, $quantity, $available, $type, $notaNumber, $note);

        return $stock;
    }

    /**
     * Restock satu produk di satu outlet. $quantities berisi
     * [variant_id => qty], atau ['single' => qty] untuk produk tanpa varian.
     *
     * @return int jumlah baris stok yang bertambah
     */
    public function restock(int $productId, int $outletId, array $quantities, ?string $notaNumber = null): int
    {
        $notaNumber = trim((string) $notaNumber) ?: null;

        return DB::transaction(function () use ($productId, $outletId, $quantities, $notaNumber) {
            $added = 0;

            foreach ($quantities as $variantId => $qty) {
                if ((int) $qty <= 0) {
                    continue;
                }

                $this->increase($productId, $variantId === 'single' ? null : (int) $variantId, $outletId, (int) $qty, $notaNumber);
                $added++;
            }

            return $added;
        });
    }

    /**
     * Stock opname: samakan stok sistem dengan hasil hitung fisik.
     * $counts: list of ['product_id', 'variant_id', 'counted'].
     *
     * @return int jumlah baris yang berubah
     */
    public function opname(int $outletId, array $counts, ?string $note = null): int
    {
        $note = trim((string) $note) ?: 'Stock opname';

        return DB::transaction(function () use ($outletId, $counts, $note) {
            $changed = 0;

            foreach ($counts as $row) {
                $counted = (int) $row['counted'];
                if ($counted < 0) {
                    throw new PosException('Hasil hitung fisik tidak boleh minus.');
                }

                $stock = $this->lockedStock($row['product_id'], $row['variant_id'], $outletId);
                $diff = $counted - $stock->quantity;

                if ($diff === 0) {
                    continue;
                }

                $before = $stock->quantity;
                $stock->update(['quantity' => $counted]);
                $this->record($stock, abs($diff), $before, $diff > 0 ? 'adjustment_in' : 'adjustment_out', null, $note);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Pindahkan stok antar outlet. $items: list of
     * ['product_id', 'variant_id', 'name', 'quantity'].
     *
     * @throws PosException
     * @throws InsufficientStockException
     */
    public function transfer(int $fromOutletId, int $toOutletId, array $items, ?string $note = null): StockTransfer
    {
        if ($fromOutletId === $toOutletId) {
            throw new PosException('Outlet asal dan tujuan tidak boleh sama.');
        }

        if (!Outlet::whereKey($toOutletId)->where('is_active', true)->exists()) {
            throw new PosException('Outlet tujuan tidak valid.');
        }

        $items = array_values(array_filter($items, fn($i) => (int) $i['quantity'] > 0));

        if (empty($items)) {
            throw new PosException('Belum ada barang yang akan ditransfer.', 'Transfer Kosong', 'warning');
        }

        return DB::transaction(function () use ($fromOutletId, $toOutletId, $items, $note) {
            $transfer = StockTransfer::create([
                'reference' => $this->nextTransferReference(),
                'from_outlet_id' => $fromOutletId,
                'to_outlet_id' => $toOutletId,
                'user_id' => auth()->id(),
                'note' => trim((string) $note) ?: null,
            ]);

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $this->decrease($item['product_id'], $item['variant_id'], $fromOutletId, $qty, $transfer->reference, $item['name'], 'transfer_out', $note);
                $this->increase($item['product_id'], $item['variant_id'], $toOutletId, $qty, $transfer->reference, 'transfer_in', $note);

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['variant_id'],
                    'item_name' => $item['name'],
                    'quantity' => $qty,
                ]);
            }

            return $transfer;
        });
    }

    protected function nextTransferReference(): string
    {
        $prefix = 'TRF-' . now()->format('ymd') . '-';
        $last = StockTransfer::where('reference', 'like', $prefix . '%')->lockForUpdate()->count();

        return $prefix . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }

    protected function lockedStock(int $productId, ?int $variantId, int $outletId): Stock
    {
        $stock = Stock::where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('outlet_id', $outletId)
            ->lockForUpdate()
            ->first();

        return $stock ?? Stock::create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'outlet_id' => $outletId,
            'quantity' => 0,
        ]);
    }

    protected function record(Stock $stock, int $quantity, int $before, string $type, ?string $notaNumber, ?string $note): void
    {
        StockHistory::create([
            'product_id' => $stock->product_id,
            'product_variant_id' => $stock->product_variant_id,
            'outlet_id' => $stock->outlet_id,
            'user_id' => auth()->id(),
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $stock->quantity,
            'type' => $type,
            'nota_number' => $notaNumber,
            'note' => $note,
        ]);
    }
}
