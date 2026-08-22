<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\StockImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockTemplateExport;

class StockController extends Controller
{
    public function importExcel(Request $request)
    {
        $request->validate([
            'file_excel' => 'required|mimes:xlsx,xls,csv|max:2048',
        ], [
            'file_excel.required' => 'Pilih file Excel terlebih dahulu.',
            'file_excel.mimes' => 'Format file harus .xlsx atau .csv',
        ]);

        $outletId = session('current_outlet_id');

        try {
            $import = new StockImport($outletId);

            Excel::import($import, $request->file('file_excel'));

            if (count($import->failedRows) > 0) {

                $errorMessage = implode('<br> • ', $import->failedRows);

                return redirect()->back()->with(
                    'warning',
                    "Selesai diproses! <b>{$import->importedCount} stok berhasil ditambahkan.</b><br><br>
                     ⚠️ Namun, ada beberapa baris yang ditolak:<br> • {$errorMessage}"
                );
            }

            return redirect()->back()->with('success', "Yeay! Semua data ({$import->importedCount} stok) berhasil di-restock.");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new StockTemplateExport, 'template_restock_stok.xlsx');
    }
}