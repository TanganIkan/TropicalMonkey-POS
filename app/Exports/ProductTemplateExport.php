<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            [
                'Footwear',       // category
                'Sepatu Sneakers',// name
                'Velvet',         // brand
                'SPT-SNK-02',     // sku (Wajib unik)
                '899123456789',   // barcode
                '150000',         // cost_price
                '300000',         // sell_price
                '1',              // is_active (1 = Aktif, 0 = Draf)
                '10',             // stock (Stok awal)
                '',               // variant_size (Kosongkan jika bukan varian)
                ''                // variant_color (Kosongkan jika bukan varian)
            ],

            // --- CONTOH 2: PRODUK BERVARIAN (Baris 1) ---
            [
                'Apparel',
                'Kemeja Flanel',  // name (Sama dengan baris di bawahnya)
                'Velvet',
                'KMJ-FLN-M-HTM',  // sku (Unik per ukuran/warna)
                '',               // barcode (Boleh kosong)
                '80000',
                '150000',
                '1',
                '24',
                'M',              // variant_size
                'Hitam'           // variant_color
            ],

            // --- CONTOH 3: PRODUK BERVARIAN (Baris 2) ---
            [
                'Apparel',
                'Kemeja Flanel',  // name (Harus persis sama agar sistem tahu ini produk yang sama)
                'Velvet',
                'KMJ-FLN-L-HTM',  // sku (Unik per ukuran/warna)
                '',               // barcode 
                '80000',
                '150000',
                '1',
                '15',             // stock (Bisa berbeda tiap varian)
                'L',              // variant_size
                'Hitam'           // variant_color
            ]
        ];
    }

    /**
     * Mendefinisikan judul kolom (Header)
     * Pastikan penamaannya sama persis dengan yang dibaca di ProductsImport
     */
    public function headings(): array
    {
        return [
            'category',
            'name',
            'brand',
            'sku',
            'barcode',
            'cost_price',
            'sell_price',
            'is_active',
            'stock',
            'variant_size',  // Kolom baru untuk Ukuran (S, M, L, dll)
            'variant_color'  // Kolom baru untuk Warna
        ];
    }

    /**
     * Memberikan styling dasar pada file Excel
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Baris 1 (Header) dicetak tebal
            1 => ['font' => ['bold' => true]],
        ];
    }
}