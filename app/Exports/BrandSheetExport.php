<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BrandSheetExport implements FromView, WithTitle, ShouldAutoSize
{
    protected $data;
    protected $title;
    protected $periodRange;
    protected $outletName;

    public function __construct($data, $title, $periodRange, $outletName)
    {
        $this->data = $data;
        $this->title = $title;
        $this->periodRange = $periodRange;
        $this->outletName = $outletName;
    }

    public function view(): View
    {
        return view('exports.brand-sheet', [
            'data' => $this->data,
            'periodRange' => $this->periodRange,
            'outletName' => $this->outletName,
        ]);
    }

    public function title(): string
    {
        return substr(str_replace(['*', ':', '/', '\\', '?', '[', ']'], '', $this->title), 0, 31);
    }
}