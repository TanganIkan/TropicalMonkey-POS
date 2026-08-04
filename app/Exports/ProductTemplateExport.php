<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * Memberikan baris contoh agar kasir/admin paham cara mengisinya
     */
    public function array(): array
    {
        return [
            [
                'Apparel',        // category
                'Kemeja Flanel',  // name
                'Velvet',         // brand
                'KMJ-FLN-01',     // sku (Wajib diisi)
                '899123456789',   // barcode
                '80000',          // cost_price
                '150000',         // sell_price
                '1',              // is_active (1 = Aktif, 0 = Draf)
                '24'              // stock (Stok awal untuk outlet ini)
            ],
            [
                'Footwear',
                'Sepatu Sneakers',
                'Velvet',
                'SPT-SNK-02',
                '',               // Barcode boleh kosong
                '150000',
                '300000',
                '1',
                '10'
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
            'stock' // Kolom baru untuk injeksi stok ke cabang
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