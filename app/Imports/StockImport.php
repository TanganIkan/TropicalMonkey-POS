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

class StockImport implements ToCollection, WithHeadingRow
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

                $stockQty = (int) ($row['stock'] ?? 0);
                $notaNumber = $row['nota_number'] ?? null;

                if ($stockQty <= 0 || empty($barcode)) {
                    continue;
                }

                $product = Product::where('barcode', $barcode)->first();
                $variant = null;

                if (!$product) {
                    $variant = ProductVariant::where('barcode', $barcode)->first();
                    if ($variant) {
                        $product = $variant->product; // Ambil data induk produknya
                    }
                }

                if (!$product && !$variant) {
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Tidak terdaftar di sistem.";
                    continue;
                }

                $productId = $product->id;
                $variantId = $variant ? $variant->id : null;

                if (!empty($notaNumber)) {
                    $notaExists = StockHistory::where('nota_number', $notaNumber)
                        ->where('product_id', $productId)
                        ->when($variantId, function ($query) use ($variantId) {
                            return $query->where('product_variant_id', $variantId);
                        })
                        ->where('outlet_id', $this->outlet_id)
                        ->exists();

                    if ($notaExists) {
                        $this->failedRows[] = "Barcode {$barcode} ditolak: Nota {$notaNumber} sudah pernah dimasukkan.";
                        continue;
                    }
                }

                StockHistory::create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'outlet_id' => $this->outlet_id,
                    'quantity' => $stockQty,
                    'type' => 'in',
                    'nota_number' => $notaNumber,
                ]);

                $stock = Stock::firstOrNew([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'outlet_id' => $this->outlet_id,
                ]);

                $stock->quantity = $stock->quantity + $stockQty;
                $stock->save();

                $this->importedCount++;
            }
        });
    }
}