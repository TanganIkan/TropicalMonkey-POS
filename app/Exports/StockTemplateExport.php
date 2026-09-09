<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['1112010606145', '10', 'INV-2026-001'],
            ['9876543210123', '25', 'INV-2026-002'],
        ];
    }

    public function headings(): array
    {
        return [
            'barcode',
            'stock',
            'nota_number'
        ];
    }
}