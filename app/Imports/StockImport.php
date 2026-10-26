<?php

namespace App\Imports;

use App\Models\StockHistory;
use App\Services\ProductService;
use App\Services\StockService;
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

                // Pencarian sama persis dengan scan di POS (barcode/SKU, varian dulu)
                $found = app(ProductService::class)->findByCode($barcode);

                if (!$found) {
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Tidak terdaftar di sistem.";
                    continue;
                }

                [$product, $variant] = $found;

                if (!$variant && $product->has_variants) {
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Produk bervarian, gunakan barcode/SKU variannya.";
                    continue;
                }

                if ($variant && !$variant->is_active) {
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Varian {$variant->label()} sudah dinonaktifkan.";
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

                app(StockService::class)->increase($productId, $variantId, $this->outlet_id, $stockQty, $notaNumber);

                $this->importedCount++;
            }
        });
    }
}