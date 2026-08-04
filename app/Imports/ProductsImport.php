<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Stock; // Jangan lupa panggil model Stock
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection; // Ubah dari ToModel ke ToCollection
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsErrors;

// Ganti implements ToModel menjadi ToCollection
class ProductsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsOnError
{
    use SkipsFailures, SkipsErrors;

    public function collection(Collection $rows)
    {
        // Ambil ID Outlet dari kasir yang sedang login
        $currentOutletId = session('current_outlet_id');

        foreach ($rows as $row) {
            $category = Category::firstOrCreate([
                'name' => trim($row['category']),
            ]);

            // 1. UPDATE OR CREATE MASTER PRODUK
            // Jika SKU sudah ada, perbarui nama dan harganya. Jika belum, buat baru.
            $product = Product::updateOrCreate(
                ['sku' => trim($row['sku'])], // Cari berdasarkan SKU
                [
                    'category_id' => $category->id,
                    'name' => trim($row['name']),
                    'brand' => $row['brand'] ?? null,
                    'barcode' => !empty($row['barcode']) ? trim($row['barcode']) : null,
                    'cost_price' => $row['cost_price'] ?? 0,
                    'sell_price' => $row['sell_price'],
                    'is_active' => $row['is_active'] ?? 1,
                ]
            );

            // 2. UPDATE OR CREATE STOK OUTLET
            // Jika stok di outlet ini sudah ada, timpa dengan kuantitas yang baru dari Excel.
            if ($currentOutletId) {
                Stock::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'outlet_id' => $currentOutletId,
                        'product_variant_id' => null,
                    ],
                    [
                        'quantity' => $row['stock'] ?? 0
                    ]
                );
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.name' => 'required|min:3',
            '*.category' => 'required',
            '*.sell_price' => 'required|numeric|min:0',
            '*.cost_price' => 'nullable|numeric|min:0',
            // Hapus aturan unique pada SKU/Barcode agar tidak error jika produk sudah ada 
            // dan kasir hanya ingin memperbarui stoknya saja melalui Excel.
            '*.sku' => 'required',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.name.required' => 'Nama produk wajib diisi.',
            '*.name.min' => 'Nama produk minimal 3 karakter.',
            '*.category.required' => 'Kategori wajib diisi.',
            '*.sell_price.required' => 'Harga jual wajib diisi.',
            '*.sell_price.numeric' => 'Harga jual harus berupa angka.',
            '*.sku.required' => 'SKU wajib diisi sebagai identitas unik.',
        ];
    }
}