<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class StockSheetExport implements FromView, WithTitle, ShouldAutoSize
{
    public function __construct(
        protected string $view,
        protected string $title,
        protected array $data,
    ) {}

    public function view(): View
    {
        return view($this->view, $this->data);
    }

    public function title(): string
    {
        // Nama sheet Excel maks. 31 karakter dan tanpa karakter khusus
        return substr(str_replace(['*', ':', '/', '\\', '?', '[', ']'], '', $this->title), 0, 31) ?: 'Brand';
    }
}
