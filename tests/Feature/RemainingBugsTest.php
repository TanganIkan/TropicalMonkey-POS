<?php

namespace Tests\Feature;

use App\Exceptions\CartChangedException;
use App\Exceptions\PosException;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class RemainingBugsTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    private function sell($product, $outlet, $user, int $qty = 1, $variant = null, string $method = 'qris'): Transaction
    {
        $key = $product->id . '-' . ($variant?->id ?? 'single');
        $name = $variant ? "{$product->name} ({$variant->size} - {$variant->color})" : $product->name;

        return $this->checkout(
            [$key => ['name' => $name, 'price' => $product->sell_price, 'quantity' => $qty, 'stock' => 99]],
            $outlet->id, $user->id, $method,
        );
    }

    // A5
    public function test_failed_product_delete_keeps_the_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/kaos.jpg', 'img');
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct(['image' => 'products/kaos.jpg']);
        $this->setStock($product, $outlet, 5);
        $this->sell($product, $outlet, User::factory()->create());

        try {
            app(ProductService::class)->delete($product);
        } catch (PosException) {
        }

        Storage::disk('public')->assertExists('products/kaos.jpg');
    }

    public function test_successful_product_delete_removes_the_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/kaos.jpg', 'img');
        $product = $this->makeProduct(['image' => 'products/kaos.jpg']);

        app(ProductService::class)->delete($product);

        Storage::disk('public')->assertMissing('products/kaos.jpg');
    }

    // A6
    public function test_variant_with_history_is_deactivated_not_deleted(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct();
        $used = $this->makeVariant($product, 'M');
        $fresh = $this->makeVariant($product, 'L');
        app(\App\Services\StockService::class)->restock($product->id, $outlet->id, [$used->id => 3]);
        $products = app(ProductService::class);

        $this->assertSame('deactivated', $products->deleteVariant($used->id));
        $this->assertSame('deleted', $products->deleteVariant($fresh->id));

        $this->assertFalse($used->fresh()->is_active);
        $this->assertNull(ProductVariant::find($fresh->id));
        $this->assertSame(1, StockHistory::where('product_variant_id', $used->id)->count());

        // Varian nonaktif tidak bisa di-scan di POS
        $this->expectException(PosException::class);
        app(CartService::class)->resolveCode($used->sku);
    }

    public function test_inactive_variant_can_be_reactivated_from_add_stock_page(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct();
        $variant = $this->makeVariant($product);
        $variant->update(['is_active' => false]);
        $this->loginAt(User::factory()->create(), $outlet);

        Volt::test('products.add-stock', ['product' => $product])
            ->assertSee('Nonaktif')
            ->call('activateVariant', $variant->id)
            ->assertSet("addQuantities.{$variant->id}", 0);

        $this->assertTrue($variant->fresh()->is_active);
    }

    // A7
    public function test_checkout_stops_when_price_changed_after_adding_to_cart(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct(['sell_price' => 100000]);
        $this->setStock($product, $outlet, 5);
        $cart = app(CartService::class)->add([], $product, null, $outlet->id);

        $product->update(['sell_price' => 120000]);

        try {
            $this->checkout($cart, $outlet->id, User::factory()->create()->id, 'qris');
            $this->fail('Checkout seharusnya dihentikan.');
        } catch (CartChangedException $e) {
            $this->assertEquals(120000, $e->cart["{$product->id}-single"]['price']);
            $this->assertStringContainsString('berubah', $e->getMessage());
        }

        $this->assertSame(0, Transaction::count());
        $this->assertSame(5, $this->stockOf($product, $outlet));
    }

    public function test_checkout_page_shows_updated_cart_after_price_change(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct(['sell_price' => 100000]);
        $this->setStock($product, $outlet, 5);
        $user = User::factory()->create();
        $this->loginAt($user, $outlet);
        $this->openShiftFor($user, $outlet);
        session(['pos_active_cart' => app(CartService::class)->add([], $product, null, $outlet->id)]);

        $component = Volt::test('pos.checkout');
        $product->update(['sell_price' => 90000]);

        $component->call('selectPayment', 'qris')
            ->call('processPayment')
            ->assertNoRedirect()
            ->assertSet("cart.{$product->id}-single.price", 90000);

        $this->assertSame(0, Transaction::count());
    }

    // A8
    public function test_order_ids_are_sequential_per_day(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 10);
        // Format lama (acak) di hari yang sama tidak mengganggu urutan
        Transaction::create(['order_id' => 'TRX-' . now()->format('ymd') . '-ZK3QA', 'outlet_id' => $outlet->id, 'user_id' => $user->id, 'subtotal' => 1, 'total' => 1, 'payment_method' => 'qris']);

        $first = $this->sell($product, $outlet, $user);
        $second = $this->sell($product, $outlet, $user);

        $this->assertSame('TRX-' . now()->format('ymd') . '-0001', $first->order_id);
        $this->assertSame('TRX-' . now()->format('ymd') . '-0002', $second->order_id);
    }

    // B4
    public function test_codes_must_be_unique_across_products_and_variants(): void
    {
        $outlet = $this->makeOutlet();
        $withVariant = $this->makeProduct(['name' => 'Kaos Varian']);
        $variant = $this->makeVariant($withVariant);
        $products = app(ProductService::class);

        $this->assertTrue($products->codeTaken($variant->barcode));
        $this->assertTrue($products->codeTaken($withVariant->sku));
        $this->assertFalse($products->codeTaken('BELUM-ADA'));

        $this->expectException(PosException::class);
        $this->expectExceptionMessage('sudah dipakai');

        $products->create([
            'name' => 'Topi', 'category_id' => $withVariant->category_id, 'brand_id' => '',
            'cost_price' => 0, 'sell_price' => 50000, 'sku' => 'TOPI-1', 'barcode' => $variant->barcode,
            'has_variants' => false, 'is_active' => 1, 'stock' => 0, 'variants' => [],
        ], null, $outlet->id);
    }

    public function test_add_stock_page_rejects_barcode_used_by_another_product(): void
    {
        $outlet = $this->makeOutlet();
        $other = $this->makeProduct(['name' => 'Topi']);
        $product = $this->makeProduct();
        $this->makeVariant($product);
        $this->loginAt(User::factory()->create(), $outlet);

        Volt::test('products.add-stock', ['product' => $product])
            ->set('newSize', 'XL')->set('newColor', 'Red')->set('newSku', 'BARU-XL')
            ->set('newBarcode', $other->barcode)
            ->call('saveNewVariant')
            ->assertHasErrors('newBarcode');
    }

    // E1 + E2
    public function test_best_sellers_group_variants_under_current_product_name(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $kaos = $this->makeProduct(['name' => 'Kaos Lama']);
        $m = $this->makeVariant($kaos, 'M');
        $l = $this->makeVariant($kaos, 'L');
        $topi = $this->makeProduct(['name' => 'Topi']);
        foreach ([[$kaos, $m], [$kaos, $l], [$topi, null]] as [$p, $v]) {
            $this->setStock($p, $outlet, 10, $v);
        }

        $this->sell($kaos, $outlet, $user, 2, $m);
        $this->sell($kaos, $outlet, $user, 2, $l);
        $this->sell($topi, $outlet, $user, 3, null, 'kartu_non_lokal');
        $kaos->update(['name' => 'Kaos Baru']);

        $reports = app(ReportService::class);
        [$start, $end] = $reports->range('daily');
        $summary = $reports->summary($start, $end);

        $this->assertSame('Kaos Baru', $summary['topProduct']->product_name);
        $this->assertEquals(4, $summary['topProduct']->total_sold);
        $this->assertCount(2, $summary['topSelling']);
        $this->assertCount(2, $reports->topProducts()->items());

        $brandTotal = collect($reports->brandRevenue($start, $end))->sum('total_revenue');
        $this->assertEquals($brandTotal, $summary['totalRevenue']);
        $this->assertGreaterThan(0, $summary['taxNonLokal']);
    }

    // H1
    public function test_owner_cannot_change_own_role(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $this->loginAt($owner, $outlet);

        Volt::test('staff.index')
            ->call('openForm', $owner->id)
            ->set('role', 'staff')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertSame('owner', $owner->fresh()->role);
    }

    // H2 + C2: nama yang sudah ada tidak lagi digabung otomatis (varian ditambah lewat Kelola Stok & Varian)
    public function test_creating_product_with_existing_name_is_rejected(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct(['name' => 'Kaos Monyet']);
        $this->makeVariant($product, 'M', 'Black');

        try {
            app(ProductService::class)->create([
                'name' => 'Kaos Monyet', 'category_id' => $product->category_id, 'brand_id' => '',
                'cost_price' => 0, 'sell_price' => 100000, 'sku' => '', 'barcode' => '',
                'has_variants' => true, 'is_active' => 1, 'stock' => 0,
                'variants' => [['size' => 'M', 'color' => 'Black', 'stock' => 5], ['size' => 'L', 'color' => 'Black', 'stock' => 5]],
            ], null, $outlet->id);
            $this->fail('Seharusnya ditolak.');
        } catch (PosException $e) {
            $this->assertStringContainsString('sudah terdaftar', $e->getMessage());
        }

        $this->assertSame(1, $product->variants()->count());
    }

    // H3
    public function test_generated_variant_sku_gets_suffix_when_taken(): void
    {
        $category = Category::create(['name' => 'Kaos']);
        $products = app(ProductService::class);
        $this->makeProduct(['sku' => 'KAOS-MONYET-M-BLACK']);

        $this->assertSame('KAOS-MONYET-M-BLACK-2', $products->variantSku('Kaos Monyet!', 'M', 'Black'));
        $this->assertSame('KAOS-PISANG-M-BLACK', $products->variantSku('Kaos Pisang', 'M', 'Black'));
    }

    // H4
    public function test_staff_can_be_saved_without_email(): void
    {
        $outlet = $this->makeOutlet();
        $this->loginAt(User::factory()->owner()->create(), $outlet);

        Volt::test('staff.index')
            ->call('openForm')
            ->set('name', 'Kadek')->set('username', 'kadek')->set('email', '')->set('password', 'rahasia1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(User::where('username', 'kadek')->value('email'));
    }

    // D7
    public function test_receipt_auto_prints_only_right_after_payment(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $this->loginAt($user, $outlet);
        $this->openShiftFor($user, $outlet);
        session(['pos_active_cart' => app(CartService::class)->add([], $product, null, $outlet->id)]);

        Volt::test('pos.checkout')->call('selectPayment', 'qris')->call('processPayment');
        $trx = Transaction::first();

        Volt::test('pos.receipt', ['transaction' => $trx])->assertSet('autoPrint', true);

        // Kunjungan berikutnya (refresh) tidak mencetak otomatis
        session()->ageFlashData();
        session()->ageFlashData();
        Volt::test('pos.receipt', ['transaction' => $trx])->assertSet('autoPrint', false);
    }
}
