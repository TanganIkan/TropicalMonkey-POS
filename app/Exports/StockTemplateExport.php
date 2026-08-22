<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * Data contoh atau panduan pengisian untuk klien
     */
    public function array(): array
    {
        return [
            // Contoh 1: Restock barang tunggal
            [
                'TND-DM-4P',     // sku (Harus sama persis dengan yang ada di sistem)
                '15',            // stock (Jumlah barang yang masuk)
                'INV-2026-100',  // nota_number (Opsional, tapi disarankan)
            ],

            // Contoh 2: Restock barang bervarian (misal warna hitam)
            [
                'CRR-60L-HTM',
                '5',
                'INV-2026-101',
            ],

            // Contoh 3: Restock tanpa nota (nota dibiarkan kosong)
            [
                'SB-POLAR-01',
                '20',
                '',
            ]
        ];
    }

    /**
     * Mendefinisikan judul kolom (Header)
     * Pastikan penamaannya cocok dengan array key di StockImport ($row['sku'], $row['stock'], dll)
     */
    public function headings(): array
    {
        return [
            'sku',           // Wajib diisi klien
            'stock',         // Wajib diisi klien (jumlah barang masuk)
            'nota_number'    // Boleh kosong
        ];
    }

    /**
     * Memberikan styling pada file Excel
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Baris 1 (Header) dicetak tebal dengan sedikit penyesuaian agar lebih profesional
            1 => [
                'font' => ['bold' => true, 'size' => 12],
            ],
        ];
    }
}