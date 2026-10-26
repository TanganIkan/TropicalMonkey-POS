<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Sisa stok per brand: 1 sheet ringkasan + 1 sheet per brand.
 */
class BrandStockExport implements WithMultipleSheets
{
    public function __construct(
        protected array $brands,
        protected array $outlets, // [outlet_id => nama]
        protected string $dateLabel,
    ) {}

    public function sheets(): array
    {
        $sheets = [new StockSheetExport('exports.stock-summary', 'Ringkasan', [
            'brands' => $this->brands,
            'outlets' => $this->outlets,
            'dateLabel' => $this->dateLabel,
        ])];

        foreach ($this->brands as $brandName => $data) {
            $sheets[] = new StockSheetExport('exports.stock-brand-sheet', $brandName, [
                'data' => $data,
                'outlets' => $this->outlets,
                'dateLabel' => $this->dateLabel,
            ]);
        }

        return $sheets;
    }
}
