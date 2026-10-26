<?php

namespace Tests\Feature;

use App\Exceptions\PosException;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\User;
use App\Services\ProductService;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class StockAndProductTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    public function test_restock_adds_stock_and_history_with_nota(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct();
        $m = $this->makeVariant($product, 'M');
        $l = $this->makeVariant($product, 'L');

        $added = app(StockService::class)->restock($product->id, $outlet->id, [$m->id => 5, $l->id => 0], ' NOTA-01 ');

        $this->assertSame(1, $added);
        $this->assertSame(5, $this->stockOf($product, $outlet, $m));
        $this->assertDatabaseHas('stock_histories', ['product_variant_id' => $m->id, 'type' => 'restock', 'quantity' => 5, 'nota_number' => 'NOTA-01', 'stock_before' => 0, 'stock_after' => 5]);
        $this->assertSame(1, StockHistory::count());
    }

    public function test_create_product_with_variants_initialises_stock_for_every_outlet(): void
    {
        $active = $this->makeOutlet('Tropical Monkey');
        $other = $this->makeOutlet('Tropisoul');
        $category = \App\Models\Category::create(['name' => 'Kaos']);

        $product = app(ProductService::class)->create([
            'name' => 'Kaos Pisang', 'category_id' => $category->id, 'brand_id' => '',
            'cost_price' => '', 'sell_price' => 120000, 'sku' => '', 'barcode' => '',
            'has_variants' => true, 'is_active' => 1, 'stock' => 0,
            'variants' => [
                ['size' => 'M', 'color' => 'Black', 'stock' => 3],
                ['size' => 'L', 'color' => 'Black', 'stock' => 0],
            ],
        ], null, $active->id);

        $m = $product->variants()->where('size', 'M')->first();
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(3, $this->stockOf($product, $active, $m));
        $this->assertSame(0, $this->stockOf($product, $other, $m));
        $this->assertSame(4, \App\Models\Stock::where('product_id', $product->id)->count());
        // Riwayat hanya untuk stok yang benar-benar masuk
        $this->assertSame(1, StockHistory::count());
    }

    public function test_create_rejects_duplicate_name_without_variants(): void
    {
        $outlet = $this->makeOutlet();
        $existing = $this->makeProduct(['name' => 'Kaos Monyet']);

        $this->expectException(PosException::class);

        app(ProductService::class)->create([
            'name' => 'Kaos Monyet', 'category_id' => $existing->category_id, 'brand_id' => '',
            'cost_price' => 0, 'sell_price' => 1, 'sku' => '', 'barcode' => '',
            'has_variants' => false, 'is_active' => 1, 'stock' => 0, 'variants' => [],
        ], null, $outlet->id);
    }

    public function test_product_with_sales_cannot_be_deleted(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $this->checkout(
            ["{$product->id}-single" => ['name' => 'Kaos', 'price' => 100000, 'quantity' => 1, 'stock' => 5]],
            $outlet->id, $user->id, 'qris',
        );

        try {
            app(ProductService::class)->delete($product);
            $this->fail('Produk dengan transaksi seharusnya tidak bisa dihapus.');
        } catch (PosException $e) {
            $this->assertSame('Tidak Bisa Dihapus!', $e->title);
        }

        $this->assertNotNull(Product::find($product->id));
        $this->assertSame(4, $this->stockOf($product, $outlet));
    }

    public function test_report_summary_matches_transactions(): void
    {
        $outlet = $this->makeOutlet();
        $other = $this->makeOutlet('Tropisoul');
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 10);
        $this->setStock($product, $other, 10);
        $checkout = app(\App\Services\CheckoutService::class);
        $item = fn($qty) => ["{$product->id}-single" => ['name' => 'Kaos Monyet', 'price' => 100000, 'quantity' => $qty, 'stock' => 10]];

        $this->checkout($item(2), $outlet->id, $user->id, 'kartu_non_lokal');
        $this->checkout($item(1), $other->id, $user->id, 'qris');

        $reports = app(ReportService::class);
        [$start, $end] = $reports->range('daily');

        $all = $reports->summary($start, $end);
        $this->assertSame(2, $all['totalTransactions']);
        // E1: pendapatan = subtotal (tanpa pajak kartu luar), sama dengan laporan per brand
        $this->assertEquals(300000, $all['totalRevenue']);
        $this->assertEquals(3, $all['totalItemsSold']);
        $this->assertEquals(6000, $all['taxNonLokal']);
        $this->assertSame('Kaos Monyet', $all['topProduct']->product_name);

        $one = $reports->summary($start, $end, $other->id);
        $this->assertSame(1, $one['totalTransactions']);
        $this->assertEquals(100000, $one['totalRevenue']);

        $brands = $reports->brandRevenue($start, $end);
        $this->assertEquals(300000, $brands['Tanpa Brand']['total_revenue']);
    }
}
