<?php

namespace App\Imports;

use App\Exceptions\InsufficientStockException;
use App\Services\ProductService;
use App\Services\StockService;
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

                // 1. CARI BARANG (sama persis dengan scan di POS)
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

                $productId = $product->id;
                $variantId = $variant ? $variant->id : null;

                // 2. POTONG STOK & CATAT HISTORI (ditolak bila stok tidak cukup)
                try {
                    app(StockService::class)->decrease($productId, $variantId, $this->outlet_id, $qtyRetur, null, "Barcode {$barcode}", 'return', $alasan);
                } catch (InsufficientStockException $e) {
                    $this->failedRows[] = "Barcode {$barcode} ditolak: Stok tidak cukup (Sisa: {$e->available}, Mau ditarik: {$qtyRetur}).";
                    continue;
                }

                $this->importedCount++;
            }
        });
    }
}