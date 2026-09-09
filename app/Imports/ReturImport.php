<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ReturImport implements ToCollection, WithHeadingRow
{
    public $importedCount = 0;
    public $failedRows = [];
    protected $outlet_id;

    public function __construct($outlet_id)
    {
        $this->outlet_id = $outlet_id;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $barcode = trim($row['barcode'] ?? '');
                $qtyRetur = (int) ($row['qty_retur'] ?? 0);
                $alasan = $row['alasan'] ?? 'RETUR EXCEL';

                if ($qtyRetur <= 0 || empty($barcode)) {
                    continue;
                }

                // 1. CARI BARANG
                $product = Product::where('barcode', $barcode)->first();
                $variant = null;

                if (!$product) {
                    $variant = ProductVariant::where('barcode', $barcode)->first();
                    if ($variant) {
                        $product = $variant->product;
                    }
                }

                if (!$product && !$variant) {
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Tidak terdaftar di sistem.";
                    continue;
                }

                $productId = $product->id;
                $variantId = $variant ? $variant->id : null;

                // 2. CEK STOK DI OUTLET
                $stockRecord = Stock::where('outlet_id', $this->outlet_id)
                    ->where('product_id', $productId);

                if ($variantId) {
                    $stockRecord->where('product_variant_id', $variantId);
                } else {
                    $stockRecord->where(function ($q) {
                        $q->whereNull('product_variant_id')->orWhere('product_variant_id', 0);
                    });
                }

                $stock = $stockRecord->first();

                // 3. VALIDASI STOK MINUS
                if (!$stock || $stock->quantity < $qtyRetur) {
                    $sisa = $stock ? $stock->quantity : 0;
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Stok tidak cukup (Sisa: {$sisa}, Mau ditarik: {$qtyRetur}).";
                    continue;
                }

                // 4. POTONG STOK & CATAT HISTORI
                $stock->quantity -= $qtyRetur;
                $stock->save();

                StockHistory::create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'outlet_id' => $this->outlet_id,
                    'quantity' => $qtyRetur,
                    'type' => 'out',
                    'nota_number' => $alasan,
                ]);

                $this->importedCount++;
            }
        });
    }
}