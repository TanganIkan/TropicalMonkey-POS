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
        // Gunakan DB Transaction untuk keamanan data
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $sku = trim($row['sku']);
                // Mengambil nilai dari kolom 'stock' di Excel
                $stockQty = (int) ($row['stock'] ?? 0);
                $notaNumber = $row['nota_number'] ?? null;

                // Abaikan jika tidak ada penambahan stok
                if ($stockQty <= 0) {
                    continue;
                }

                // 1. CARI BARANG BERDASARKAN SKU
                // Cek di tabel produk tunggal dulu
                $product = Product::where('sku', $sku)->first();
                $variant = null;

                // Jika tidak ketemu, cari di tabel varian
                if (!$product) {
                    $variant = ProductVariant::where('sku', $sku)->first();
                    if ($variant) {
                        $product = $variant->product; // Ambil data induk produknya
                    }
                }

                // Jika SKU sama sekali tidak ada di database, tolak baris ini
                if (!$product && !$variant) {
                    $this->failedRows[] = "SKU {$sku} ditolak: Tidak terdaftar di sistem.";
                    continue;
                }

                $productId = $product->id;
                $variantId = $variant ? $variant->id : null;

                // 2. CEK DUPLIKASI NOMOR NOTA (Anti-Human Error)
                if (!empty($notaNumber)) {
                    $notaExists = StockHistory::where('nota_number', $notaNumber)
                        ->where('product_id', $productId)
                        ->when($variantId, function ($query) use ($variantId) {
                            return $query->where('product_variant_id', $variantId);
                        })
                        ->where('outlet_id', $this->outlet_id)
                        ->exists();

                    // Jika nota sudah pernah diinput untuk SKU ini, blokir!
                    if ($notaExists) {
                        $this->failedRows[] = "SKU {$sku} ditolak: Nota {$notaNumber} sudah pernah dimasukkan.";
                        continue;
                    }
                }

                // 3. CATAT KE BUKU RIWAYAT (Stock History)
                StockHistory::create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'outlet_id' => $this->outlet_id,
                    'quantity' => $stockQty,
                    'type' => 'in',
                    'nota_number' => $notaNumber,
                ]);

                // 4. UPDATE MASTER STOK (Total Akhir)
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