<?php

namespace Tests\Feature;

use App\Exports\BrandStockExport;
use App\Models\Brand;
use App\Models\User;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class StockExportTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    public function test_stock_as_of_past_date_is_reconstructed_from_history(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->makeProduct();

        // 31 Okt: restock 10. 2 Nov: terjual 3, restock 5.
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
        app(StockService::class)->restock($product->id, $outlet->id, ['single' => 10]);
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
        $this->checkout(["{$product->id}-single" => ['name' => $product->name, 'price' => 100000, 'quantity' => 3, 'stock' => 10]], $outlet->id, $user->id);
        app(StockService::class)->restock($product->id, $outlet->id, ['single' => 5]);

        $reports = app(ReportService::class);
        $endOfOctober = now()->setDate(2026, 10, 31)->endOfDay();
        $beforeAll = now()->setDate(2026, 10, 30)->endOfDay();

        $this->assertSame(12, (int) $reports->stockSnapshot(null, $outlet->id)->sum('quantity'));
        $this->assertSame(10, (int) $reports->stockSnapshot($endOfOctober, $outlet->id)->sum('quantity'));
        $this->assertSame(0, (int) $reports->stockSnapshot($beforeAll, $outlet->id)->sum('quantity'));
    }

    public function test_stock_is_grouped_per_brand_with_outlet_columns(): void
    {
        $a = $this->makeOutlet('Tropical Monkey');
        $b = $this->makeOutlet('Tropisoul');
        $brandA = Brand::create(['name' => 'Monkey']);
        $kaos = $this->makeProduct(['name' => 'Kaos', 'brand_id' => $brandA->id, 'cost_price' => 40000, 'sell_price' => 100000]);
        $m = $this->makeVariant($kaos, 'M');
        $l = $this->makeVariant($kaos, 'L');
        $topi = $this->makeProduct(['name' => 'Topi']); // tanpa brand
        $this->setStock($kaos, $a, 3, $m);
        $this->setStock($kaos, $b, 2, $m);
        $this->setStock($kaos, $a, 0, $l);
        $this->setStock($topi, $a, 4);

        $reports = app(ReportService::class);
        $brands = $reports->stockByBrand($reports->stockSnapshot());

        $this->assertSame(['Monkey', 'Tanpa Brand'], array_keys($brands));
        $this->assertCount(1, $brands['Monkey']['items']); // varian L stok 0 tidak ikut
        $this->assertSame([$a->id => 3, $b->id => 2], $brands['Monkey']['items'][0]['outlets']);
        $this->assertSame(5, $brands['Monkey']['qty']);
        $this->assertEquals(200000, $brands['Monkey']['cost_value']);
        $this->assertCount(2, $reports->stockByBrand($reports->stockSnapshot(), includeZero: true)['Monkey']['items']);
    }

    public function test_excel_file_has_summary_and_brand_sheets(): void
    {
        $outlet = $this->makeOutlet();
        $brand = Brand::create(['name' => 'Monkey']);
        $product = $this->makeProduct(['name' => 'Kaos', 'brand_id' => $brand->id, 'barcode' => '1112010001343', 'cost_price' => 40000]);
        $this->setStock($product, $outlet, 7);
        $reports = app(ReportService::class);

        $raw = Excel::raw(new BrandStockExport($reports->stockByBrand($reports->stockSnapshot()), [$outlet->id => $outlet->name], '31 Oktober 2026'), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'stock') . '.xlsx';
        file_put_contents($path, $raw);
        $book = IOFactory::load($path);
        unlink($path);

        $this->assertSame(['Ringkasan', 'Monkey'], $book->getSheetNames());
        $sheet = $book->getSheetByName('Monkey');
        // Baris 5 = data pertama: Produk, Varian, Kategori, SKU, Barcode, Stok outlet, Total, Harga Jual, Nilai Jual
        $this->assertSame('Kaos', $sheet->getCell('A5')->getValue());
        $this->assertSame('1112010001343', $sheet->getCell('E5')->getValue()); // barcode tetap teks
        $this->assertEquals(7, $sheet->getCell('G5')->getValue());
        $this->assertEquals(100000, $sheet->getCell('H5')->getValue());
        $this->assertEquals(700000, $sheet->getCell('I5')->getValue());
        $this->assertSame('Harga Jual / Unit', $sheet->getCell('H4')->getValue());
        $this->assertNull($sheet->getCell('J4')->getValue()); // tidak ada kolom HPP

        $summary = $book->getSheetByName('Ringkasan');
        $this->assertSame('Nilai Stok (Harga Jual)', $summary->getCell('E4')->getValue());
        $this->assertNull($summary->getCell('F4')->getValue());
    }

    public function test_reports_page_exports_stock(): void
    {
        Excel::fake();
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 3);
        $this->loginAt(User::factory()->owner()->create(), $outlet);

        Volt::test('reports.index')
            ->set('tab', 'inventory')
            ->assertSee('Sisa Stok per Brand')
            ->assertSee('Tanpa Brand')
            ->set('stockDate', '2026-10-31')
            ->call('exportStock');

        Excel::assertDownloaded('sisa-stok-per-brand-2026-10-31.xlsx', fn(BrandStockExport $export) => count($export->sheets()) === 2);
    }
}
