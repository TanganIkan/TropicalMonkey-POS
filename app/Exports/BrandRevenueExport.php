<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BrandRevenueExport implements WithMultipleSheets
{
    protected $brandsData;
    protected $periodRange;
    protected $outletName;

    public function __construct($brandsData, $periodRange, $outletName)
    {
        $this->brandsData = $brandsData;
        $this->periodRange = $periodRange;
        $this->outletName = $outletName;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Looping data brand seperti biasa
        foreach ($this->brandsData as $brandName => $data) {
            $sheets[] = new BrandSheetExport($data, $brandName, $this->periodRange, $this->outletName);
        }

        // PERBAIKAN: Jika tidak ada transaksi sama sekali, buat 1 sheet "Data Kosong"
        if (empty($sheets)) {
            $emptyData = [
                'brand_name' => 'Tidak Ada Data',
                'items' => [],
                'total_revenue' => 0,
                'total_qty' => 0
            ];
            $sheets[] = new BrandSheetExport($emptyData, 'Data Kosong', $this->periodRange, $this->outletName);
        }

        return $sheets;
    }
}