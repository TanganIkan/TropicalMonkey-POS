<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class ProductsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsOnError
{
    use SkipsFailures, SkipsErrors;

    public $importedCount = 0;
    public $failedSkus = [];

    public function collection(Collection $rows)
    {
        $currentOutletId = session('current_outlet_id');

        foreach ($rows as $row) {
            $sku = trim($row['sku']);

            // 1. CEK DUPLIKASI SKU
            $existsInProducts = Product::where('sku', $sku)->exists();
            $existsInVariants = ProductVariant::where('sku', $sku)->exists();

            if ($existsInProducts || $existsInVariants) {
                $this->failedSkus[] = "{$sku} (Gagal: SKU sudah terdaftar di sistem)";
                continue;
            }

            // 2. CEK KATEGORI
            $category = Category::where('name', trim($row['category']))->first();
            if (!$category) {
                $this->failedSkus[] = "{$sku} (Gagal: Kategori '" . trim($row['category']) . "' tidak ditemukan)";
                continue;
            }

            // 3. CEK BRAND
            $brandId = null;
            if (!empty($row['brand'])) {
                $brand = Brand::where('name', trim($row['brand']))->first();
                if ($brand) {
                    $brandId = $brand->id;
                }
            }

            $hasVariants = !empty($row['variant_size']) || !empty($row['variant_color']);
            $productName = trim($row['name']);

            if ($hasVariants) {
                // LOGIKA: PRODUK BERVARIAN
                $existingProduct = Product::where('name', $productName)->first();

                if ($existingProduct) {
                    if ($existingProduct->category_id != $category->id) {
                        $this->failedSkus[] = "{$sku} (Ditolak: '{$productName}' milik kategori '{$existingProduct->category->name}', bukan '" . trim($row['category']) . "')";
                        continue;
                    }

                    if ($existingProduct->brand_id != $brandId) {
                        $this->failedSkus[] = "{$sku} (Ditolak: Brand tidak cocok dengan produk induk '{$productName}')";
                        continue;
                    }

                    $product = $existingProduct;
                } else {
                    $product = Product::create([
                        'name' => $productName,
                        'category_id' => $category->id,
                        'brand_id' => $brandId,
                        'cost_price' => $row['cost_price'] ?? 0,
                        'sell_price' => $row['sell_price'],
                        'is_active' => $row['is_active'] ?? 1,
                        'has_variants' => true,
                    ]);
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'size' => $row['variant_size'] ?? null,
                    'color' => $row['variant_color'] ?? null,
                    'barcode' => !empty($row['barcode']) ? trim($row['barcode']) : null,
                ]);

                if ($currentOutletId) {
                    Stock::create([
                        'product_id' => $product->id,
                        'outlet_id' => $currentOutletId,
                        'product_variant_id' => $variant->id,
                        'quantity' => $row['stock'] ?? 0
                    ]);
                }

                $this->importedCount++;

            } else {
                // LOGIKA: PRODUK TUNGGAL

                // CEK: Apakah nama ini sudah pernah dipakai oleh produk lain di database?
                $existingName = Product::where('name', $productName)->exists();

                if ($existingName) {
                    // Blokir dan tolak baris ini!
                    $this->failedSkus[] = "{$sku} (Ditolak: Nama '{$productName}' sudah ada di database. Jika ini adalah varian, wajib isi kolom variant_size / variant_color di Excel.)";
                    continue;
                }

                $product = Product::create([
                    'sku' => $sku,
                    'category_id' => $category->id,
                    'brand_id' => $brandId,
                    'name' => $productName,
                    'barcode' => !empty($row['barcode']) ? trim($row['barcode']) : null,
                    'cost_price' => $row['cost_price'] ?? 0,
                    'sell_price' => $row['sell_price'],
                    'is_active' => $row['is_active'] ?? 1,
                    'has_variants' => false,
                ]);

                if ($currentOutletId) {
                    Stock::create([
                        'product_id' => $product->id,
                        'outlet_id' => $currentOutletId,
                        'product_variant_id' => null,
                        'quantity' => $row['stock'] ?? 0
                    ]);
                }

                $this->importedCount++;
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.name' => 'required|min:3',
            '*.category' => 'required|exists:categories,name',
            '*.brand' => 'nullable|exists:brands,name',
            '*.sell_price' => 'required|numeric|min:0',
            '*.cost_price' => 'nullable|numeric|min:0',
            '*.sku' => 'required',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.name.required' => 'Nama produk wajib diisi.',
            '*.name.min' => 'Nama minimal 3 karakter.',
            '*.category.required' => 'Kategori wajib diisi.',
            '*.category.exists' => 'Kategori tidak valid / belum didaftarkan di sistem.',
            '*.brand.exists' => 'Brand tidak valid / belum didaftarkan di sistem.',
            '*.sell_price.required' => 'Harga jual wajib.',
            '*.sell_price.numeric' => 'Harga jual harus berupa angka.',
            '*.sku.required' => 'SKU wajib diisi.',
        ];
    }
}