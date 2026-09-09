<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReturTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['1112010606145', '5', 'Retur ke Brand A'],
            ['9876543210123', '2', 'Barang Rusak'],
        ];
    }

    public function headings(): array
    {
        return [
            'barcode',
            'qty_retur',
            'alasan'
        ];
    }
}