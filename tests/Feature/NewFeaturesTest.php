<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PosException;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductService;
use App\Services\ReportService;
use App\Services\ShiftService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Volt\Volt;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class NewFeaturesTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    private function item(string $name, int $qty, float $price = 100000, ?array $discount = null): array
    {
        return array_filter(['name' => $name, 'price' => $price, 'quantity' => $qty, 'stock' => 99, 'discount' => $discount]);
    }

    // ===== Diskon =====

    public function test_item_and_order_discounts_are_applied_before_tax(): void
    {
        $calc = app(CheckoutService::class)->calculate([
            'a' => $this->item('A', 2, 100000, ['type' => 'percent', 'value' => 10]), // 200.000 - 20.000
            'b' => $this->item('B', 1, 50000, ['type' => 'amount', 'value' => 5000]),  // 50.000 - 5.000
        ], ['type' => 'amount', 'value' => 25000], [['method' => 'kartu_non_lokal']]);

        $this->assertEquals(250000, $calc['gross']);
        $this->assertEquals(25000, $calc['item_discount']);
        $this->assertEquals(225000, $calc['subtotal']);
        $this->assertEquals(200000, $calc['net']);
        $this->assertEquals(6000, $calc['tax']); // 3% dari 200.000 (setelah diskon)
        $this->assertEquals(206000, $calc['total']);
        // Diskon total belanja dibagi proporsional ke item
        $this->assertEquals(25000, $calc['lines']['a']['share'] + $calc['lines']['b']['share']);
    }

    public function test_invalid_discounts_are_rejected(): void
    {
        $checkout = app(CheckoutService::class);

        foreach ([['type' => 'percent', 'value' => 120], ['type' => 'amount', 'value' => 150000], ['type' => 'amount', 'value' => -1]] as $discount) {
            try {
                $checkout->summarize(['a' => $this->item('A', 1, 100000, $discount)], null);
                $this->fail('Diskon tidak valid seharusnya ditolak: ' . json_encode($discount));
            } catch (PosException $e) {
                $this->assertSame('Diskon Tidak Valid', $e->title);
            }
        }
    }

    public function test_discounted_transaction_is_saved_with_cost_snapshot(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct(['cost_price' => 40000, 'sell_price' => 100000]);
        $this->setStock($product, $outlet, 10);

        $trx = $this->checkout(
            ["{$product->id}-single" => $this->item($product->name, 2, 100000, ['type' => 'amount', 'value' => 20000])],
            $outlet->id, $user->id, 'qris', null, ['type' => 'percent', 'value' => 10],
        );

        $this->assertEquals(180000, $trx->subtotal);
        $this->assertEquals(18000, $trx->discount_amount);
        $this->assertEquals(162000, $trx->total);
        $item = $trx->items()->first();
        $this->assertEquals(20000, $item->discount_amount);
        $this->assertEquals(18000, $item->order_discount_share);
        $this->assertEquals(40000, $item->cost_price);
        $this->assertEquals(162000, $item->netAmount());
        $this->assertTrue(ActivityLog::where('action', 'transaction.create')->where('description', 'like', '%diskon Rp 38.000%')->exists());
    }

    // ===== Split payment & pajak per metode =====

    public function test_split_payment_taxes_only_the_card_portion(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $this->openShiftFor($user, $outlet);

        $trx = app(CheckoutService::class)->process(
            ["{$product->id}-single" => $this->item($product->name, 2)],
            $outlet->id, $user->id,
            [
                ['method' => 'tunai', 'amount' => 50000, 'cash_received' => 60000],
                ['method' => 'kartu_non_lokal', 'amount' => 150000],
            ],
        );

        $this->assertSame('split', $trx->payment_method);
        $this->assertEquals(4500, $trx->tax_amount); // 3% x 150.000
        $this->assertEquals(204500, $trx->total);
        $this->assertEquals(10000, $trx->change_amount);
        $this->assertCount(2, $trx->payments);
        $this->assertEquals(3, $trx->payments->firstWhere('payment_method', 'kartu_non_lokal')->tax_rate);
    }

    public function test_split_amounts_must_match_total(): void
    {
        $this->expectException(PosException::class);
        $this->expectExceptionMessage('harus sama dengan total belanja');

        app(CheckoutService::class)->calculate(['a' => $this->item('A', 1)], null, [
            ['method' => 'qris', 'amount' => 30000],
            ['method' => 'kartu_lokal', 'amount' => 30000],
        ]);
    }

    public function test_changing_tax_rate_does_not_change_old_transactions(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $old = $this->checkout(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $user->id, 'kartu_non_lokal');

        PaymentMethod::where('code', 'kartu_non_lokal')->update(['tax_rate' => 5]);
        Once::flush();
        $new = $this->checkout(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $user->id, 'kartu_non_lokal');

        $this->assertEquals(3, $old->payments()->first()->tax_rate);
        $this->assertEquals(3000, $old->fresh()->tax_amount);
        $this->assertEquals(5, $new->payments()->first()->tax_rate);
        $this->assertEquals(5000, $new->tax_amount);

        $this->loginAt($user, $outlet)->get('/pos/receipt/' . $old->id)->assertSee('TAX (3%)')->assertDontSee('TAX (5%)');
    }

    public function test_inactive_payment_method_is_rejected(): void
    {
        PaymentMethod::where('code', 'qris')->update(['is_active' => false]);
        Once::flush();

        $this->expectException(PosException::class);
        app(CheckoutService::class)->calculate(['a' => $this->item('A', 1)], null, [['method' => 'qris']]);
    }

    // ===== Shift =====

    public function test_checkout_opens_shift_automatically(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->owner()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);

        $trx = app(CheckoutService::class)->process(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $user->id, [['method' => 'qris']]);

        $shift = app(ShiftService::class)->current($user);
        $this->assertNotNull($shift);
        $this->assertSame($shift->id, $trx->shift_id);
    }

    public function test_staff_shift_starts_at_login_and_ends_at_logout(): void
    {
        $outlet = $this->makeOutlet();
        $other = $this->makeOutlet('Tropisoul');
        $staff = User::factory()->create(['username' => 'elga']);
        $owner = User::factory()->owner()->create(['username' => 'owner']);
        $shifts = app(ShiftService::class);
        $login = fn($username, $outletId) => Volt::test('auth.login')->set('username', $username)->set('password', 'password')
            ->call('authenticate')->set('selected_outlet_id', $outletId)->call('enterPos');

        $login('elga', $outlet->id);
        $first = $shifts->current($staff);
        $this->assertSame($outlet->id, $first->outlet_id);
        $this->assertEquals(0, $first->opening_cash);
        $this->assertNull($shifts->current($owner->id)); // owner tidak otomatis bertugas

        // Login lagi di outlet yang sama -> shift dilanjutkan
        auth()->logout();
        $login('elga', $outlet->id);
        $this->assertSame($first->id, $shifts->current($staff)->id);

        // Login di outlet lain -> shift lama ditutup otomatis
        auth()->logout();
        $login('elga', $other->id);
        $this->assertNotNull($first->fresh()->closed_at);
        $this->assertSame($other->id, $shifts->current($staff)->outlet_id);

        // Logout menutup shift tanpa hitung kas
        Volt::test('profile.index')->call('logout');
        $this->assertNull($shifts->current($staff));
        $this->assertNull(\App\Models\Shift::latest('id')->first()->cash_difference);
    }

    public function test_shift_summary_and_close_compute_cash_difference(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 10);
        $this->actingAs($user);
        $shifts = app(ShiftService::class);

        $shift = $shifts->open($user, $outlet->id, 200000);
        $this->checkout(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $user->id, 'tunai', 100000);
        $this->checkout(["{$product->id}-single" => $this->item($product->name, 2)], $outlet->id, $user->id, 'qris');

        $summary = $shifts->summary($shift);
        $this->assertSame(2, $summary['transactions']);
        $this->assertEquals(300000, $summary['net_sales']);
        $this->assertEquals(100000, $summary['cash_sales']);
        $this->assertEquals(300000, $summary['expected_cash']);

        $closed = $shifts->close($shift, 295000, 'kurang kembalian');
        $this->assertEquals(-5000, $closed->cash_difference);
        $this->assertNull($shifts->current($user));
        $this->assertTrue(ActivityLog::where('action', 'shift.close')->exists());
    }

    public function test_cannot_open_two_shifts(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $shifts = app(ShiftService::class);
        $shifts->open($user, $outlet->id, 0);

        $this->expectException(PosException::class);
        $shifts->open($user, $outlet->id, 0);
    }

    public function test_pos_page_auto_opens_shift_and_close_logs_out(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->owner()->create();
        $this->loginAt($user, $outlet);

        Volt::test('pos.index')
            ->assertDontSee('Buka Shift')
            ->assertSee('Shift sejak')
            ->set('showCloseShift', true)
            ->assertSee('Penjualan Tunai')
            ->set('closingCash', '')
            ->call('closeShift')
            ->assertRedirect('/login');

        $this->assertNull(app(ShiftService::class)->current($user));
        $this->assertGuest();
    }

    // ===== Void =====

    public function test_void_returns_stock_and_is_excluded_from_reports(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $trx = $this->checkout(["{$product->id}-single" => $this->item($product->name, 2)], $outlet->id, $owner->id);
        $this->actingAs($owner);

        app(CheckoutService::class)->void($trx->load('items'), 'Pelanggan batal');

        $this->assertSame(5, $this->stockOf($product, $outlet));
        $this->assertTrue($trx->fresh()->isVoid());
        $this->assertDatabaseHas('stock_histories', ['type' => 'void', 'quantity' => 2, 'note' => 'Pelanggan batal']);

        $reports = app(ReportService::class);
        [$start, $end] = $reports->range('daily');
        $this->assertSame(0, $reports->summary($start, $end)['totalTransactions']);
        $this->assertSame(1, $reports->cashiers($start, $end)[0]['voids']);

        $this->expectException(PosException::class);
        app(CheckoutService::class)->void($trx->fresh()->load('items'), 'lagi');
    }

    public function test_void_requires_reason_and_owner_page(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $trx = $this->checkout(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $owner->id);
        $this->loginAt($owner, $outlet);

        Volt::test('transactions.index')
            ->call('openVoid', $trx->id)
            ->set('voidReason', '')
            ->call('confirmVoid');
        $this->assertFalse($trx->fresh()->isVoid());

        Volt::test('transactions.index')
            ->call('openVoid', $trx->id)
            ->set('voidReason', 'Salah input')
            ->call('confirmVoid')
            ->assertSee('VOID');
        $this->assertTrue($trx->fresh()->isVoid());
    }

    public function test_staff_can_view_transactions_and_reports_and_void(): void
    {
        $outlet = $this->makeOutlet();
        $staff = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $trx = $this->checkout(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $staff->id);

        $this->loginAt($staff, $outlet)->get('/transactions')->assertOk()->assertSee($trx->order_id);
        foreach (['/reports', '/reports?tab=cashiers', '/reports?tab=inventory'] as $page) {
            $this->loginAt($staff, $outlet)->get($page)->assertOk();
        }

        Volt::test('transactions.index')
            ->call('openVoid', $trx->id)
            ->set('voidReason', 'Pelanggan batal')
            ->call('confirmVoid');

        $trx->refresh();
        $this->assertTrue($trx->isVoid());
        $this->assertSame($staff->id, $trx->voided_by);
        $this->assertSame(5, $this->stockOf($product, $outlet));
    }

    // ===== Harga per varian (C1) & edit varian (C3) =====

    public function test_variant_price_overrides_product_price(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct(['sell_price' => 100000]);
        $xxl = $this->makeVariant($product, 'XXL');
        $xxl->update(['sell_price' => 120000]);
        $m = $this->makeVariant($product, 'M');
        $this->setStock($product, $outlet, 5, $xxl);
        $this->setStock($product, $outlet, 5, $m);
        $carts = app(CartService::class);

        $cart = $carts->add([], $product, $xxl->fresh(), $outlet->id);
        $cart = $carts->add($cart, $product, $m->fresh(), $outlet->id);

        $this->assertEquals(120000, $cart["{$product->id}-{$xxl->id}"]['price']);
        $this->assertEquals(100000, $cart["{$product->id}-{$m->id}"]['price']);
        [, $changes] = $carts->refresh($cart);
        $this->assertEmpty($changes);
    }

    public function test_variant_and_product_codes_can_be_edited(): void
    {
        $product = $this->makeProduct();
        $other = $this->makeProduct(['name' => 'Topi']);
        $withVariant = $this->makeProduct(['name' => 'Kaos Varian']);
        $variant = $this->makeVariant($withVariant);
        $products = app(ProductService::class);

        $products->update($product, ['name' => $product->name, 'category_id' => $product->category_id, 'brand_id' => null, 'cost_price' => 1, 'sell_price' => 2, 'is_active' => 1, 'sku' => 'BARU-01', 'barcode' => '999'], null, false);
        $this->assertSame('BARU-01', $product->fresh()->sku);

        $products->updateVariant($variant, ['size' => 'L', 'color' => 'Red', 'sku' => 'KV-L-RED', 'barcode' => '', 'sell_price' => '130000', 'cost_price' => '']);
        $variant->refresh();
        $this->assertSame('L', $variant->size);
        $this->assertEquals(130000, $variant->sell_price);
        $this->assertNull($variant->cost_price);

        $this->expectException(PosException::class);
        $products->updateVariant($variant, ['size' => 'L', 'color' => 'Red', 'sku' => $other->sku]);
    }

    // ===== Stock opname & transfer =====

    public function test_stock_opname_records_adjustments(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $this->actingAs($user);
        $a = $this->makeProduct(['name' => 'A']);
        $b = $this->makeProduct(['name' => 'B']);
        $c = $this->makeProduct(['name' => 'C']);
        $this->setStock($a, $outlet, 10);
        $this->setStock($b, $outlet, 10);
        $this->setStock($c, $outlet, 10);

        $changed = app(StockService::class)->opname($outlet->id, [
            ['product_id' => $a->id, 'variant_id' => null, 'counted' => 8],
            ['product_id' => $b->id, 'variant_id' => null, 'counted' => 12],
            ['product_id' => $c->id, 'variant_id' => null, 'counted' => 10],
        ]);

        $this->assertSame(2, $changed);
        $this->assertSame(8, $this->stockOf($a, $outlet));
        $this->assertSame(12, $this->stockOf($b, $outlet));
        $this->assertDatabaseHas('stock_histories', ['product_id' => $a->id, 'type' => 'adjustment_out', 'quantity' => 2, 'user_id' => $user->id]);
        $this->assertDatabaseHas('stock_histories', ['product_id' => $b->id, 'type' => 'adjustment_in', 'quantity' => 2]);
    }

    public function test_opname_page_saves_counts(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 7);
        $this->loginAt(User::factory()->create(), $outlet);

        Volt::test('stocks.opname')
            ->assertSee($product->name)
            ->set("counts.{$product->id}-single", 4)
            ->call('save');

        $this->assertSame(4, $this->stockOf($product, $outlet));
        $this->assertTrue(ActivityLog::where('action', 'stock.opname')->exists());
    }

    public function test_transfer_moves_stock_between_outlets(): void
    {
        $from = $this->makeOutlet('Tropical Monkey');
        $to = $this->makeOutlet('Tropisoul');
        $this->actingAs(User::factory()->create());
        $product = $this->makeProduct();
        $this->setStock($product, $from, 10);
        $stocks = app(StockService::class);

        $transfer = $stocks->transfer($from->id, $to->id, [['product_id' => $product->id, 'variant_id' => null, 'name' => 'Kaos', 'quantity' => 4]], 'Stok kurang');

        $this->assertSame(6, $this->stockOf($product, $from));
        $this->assertSame(4, $this->stockOf($product, $to));
        $this->assertStringStartsWith('TRF-', $transfer->reference);
        $this->assertSame(1, StockHistory::where('type', 'transfer_out')->count());
        $this->assertSame(1, StockHistory::where('type', 'transfer_in')->count());

        $this->expectException(InsufficientStockException::class);
        $stocks->transfer($from->id, $to->id, [['product_id' => $product->id, 'variant_id' => null, 'name' => 'Kaos', 'quantity' => 99]]);
    }

    public function test_transfer_page_flow(): void
    {
        $from = $this->makeOutlet('Tropical Monkey');
        $to = $this->makeOutlet('Tropisoul');
        $product = $this->makeProduct();
        $this->setStock($product, $from, 5);
        $this->loginAt(User::factory()->create(), $from);

        Volt::test('stocks.transfer')
            ->assertSet('toOutletId', $to->id)
            ->set('search', $product->barcode)
            ->call('addItem')
            ->set('search', $product->barcode)
            ->call('addItem')
            ->call('submit')
            ->assertSee('TRF-');

        $this->assertSame(2, $this->stockOf($product, $to));
    }

    // ===== Laporan =====

    public function test_profit_inventory_and_low_stock_reports(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct(['cost_price' => 60000, 'sell_price' => 100000]);
        $this->setStock($product, $outlet, 5);
        $this->checkout(["{$product->id}-single" => $this->item($product->name, 3)], $outlet->id, $user->id);

        $reports = app(ReportService::class);
        [$start, $end] = $reports->range('daily');

        $profit = $reports->profit($start, $end);
        $this->assertEquals(300000, $profit['revenue']);
        $this->assertEquals(180000, $profit['cogs']);
        $this->assertEquals(120000, $profit['profit']);
        $this->assertEquals(40, round($profit['margin']));

        $inventory = $reports->inventoryValue($outlet->id);
        $this->assertSame(2, $inventory['qty']);
        $this->assertEquals(120000, $inventory['cost_value']);

        $low = $reports->lowStock($outlet->id, 5);
        $this->assertCount(1, $low);
        $this->assertSame(2, (int) $low->first()->quantity);
        $this->assertCount(0, $reports->lowStock($outlet->id, 1));
    }

    // ===== Pengaturan & outlet =====

    public function test_settings_page_updates_tax_rate_and_store_settings(): void
    {
        $outlet = $this->makeOutlet();
        $this->loginAt(User::factory()->owner()->create(), $outlet);
        $card = PaymentMethod::where('code', 'kartu_non_lokal')->first();

        Volt::test('settings.index')
            ->set("methods.{$card->id}.tax_rate", 2.5)
            ->call('saveMethods')
            ->assertHasNoErrors()
            ->set('lowStockThreshold', 3)
            ->set('sizeOptions', 's, m, 3xl')
            ->set('receiptFooter', "Terima kasih!\nSampai jumpa")
            ->call('saveStore')
            ->assertHasNoErrors()
            ->set('newMethodName', 'Transfer Bank')
            ->call('addMethod');

        $this->assertEquals(2.5, $card->fresh()->tax_rate);
        $this->assertSame(3, Setting::lowStockThreshold());
        $this->assertSame(['S', 'M', '3XL'], Setting::sizeOptions());
        $this->assertTrue(PaymentMethod::where('code', 'transfer_bank')->exists());

        Volt::test('products.create')->assertSet('availableSizes', ['S', 'M', '3XL']);
    }

    public function test_at_least_one_payment_method_must_stay_active(): void
    {
        $outlet = $this->makeOutlet();
        $this->loginAt(User::factory()->owner()->create(), $outlet);
        $component = Volt::test('settings.index');

        foreach (PaymentMethod::pluck('id') as $id) {
            $component->set("methods.{$id}.is_active", false);
        }

        $component->call('saveMethods')->assertHasErrors('methods');
        $this->assertTrue(PaymentMethod::where('is_active', true)->exists());
    }

    public function test_outlet_management(): void
    {
        $outlet = $this->makeOutlet();
        $this->loginAt(User::factory()->owner()->create(), $outlet);

        Volt::test('outlets.index')
            ->call('openForm')
            ->set('name', 'Outlet Baru')
            ->set('address', 'Jl. Raya Ubud')
            ->call('save')
            ->assertSee('Outlet Baru');

        $new = \App\Models\Outlet::where('name', 'Outlet Baru')->first();
        Volt::test('outlets.index')->call('toggleActive', $new->id);
        $this->assertFalse($new->fresh()->is_active);

        // Outlet yang sedang dipakai tidak bisa dinonaktifkan
        Volt::test('outlets.index')->call('toggleActive', $outlet->id);
        $this->assertTrue($outlet->fresh()->is_active);
    }

    // ===== Monitoring =====

    public function test_monitoring_shows_staff_on_duty_and_activity(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['name' => 'Kadek Kasir']);
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);
        $this->checkout(["{$product->id}-single" => $this->item($product->name, 1)], $outlet->id, $staff->id);
        $shift = app(ShiftService::class)->current($staff);

        $this->loginAt($owner, $outlet);
        Volt::test('monitoring.index')
            ->assertSee('Kadek Kasir')
            ->assertSee('Sedang Bertugas')
            ->assertSee('Transaksi TRX-')
            ->call('openCloseShift', $shift->id)
            ->set('closingCash', 0)
            ->call('closeShift');

        $this->assertNotNull($shift->fresh()->closed_at);
        $this->assertStringContainsString('Ditutup oleh owner', $shift->fresh()->closing_note);
    }

    public function test_login_and_logout_are_logged(): void
    {
        $outlet = $this->makeOutlet();
        User::factory()->create(['username' => 'elga']);

        Volt::test('auth.login')->set('username', 'elga')->set('password', 'password')->call('authenticate')
            ->set('selected_outlet_id', $outlet->id)->call('enterPos');

        $this->assertTrue(ActivityLog::where('action', 'login')->where('outlet_id', $outlet->id)->exists());
    }

    // ===== Checkout lewat halaman (diskon + split) =====

    public function test_checkout_page_with_discount_and_split_payment(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct(['sell_price' => 100000]);
        $this->setStock($product, $outlet, 5);
        $this->loginAt($user, $outlet);
        $this->openShiftFor($user, $outlet);
        $key = "{$product->id}-single";
        session(['pos_active_cart' => app(CartService::class)->add(app(CartService::class)->add([], $product, null, $outlet->id), $product, null, $outlet->id)]);

        Volt::test('pos.checkout')
            ->set("itemDiscounts.{$key}.type", 'percent')
            ->set("itemDiscounts.{$key}.value", 10) // 200.000 -> 180.000
            ->set('orderDiscountValue', 30000)      // -> 150.000
            ->call('toggleSplit')
            ->set('payments.0.method', 'tunai')
            ->set('payments.0.amount', 50000)
            ->set('payments.0.cash_received', 50000)
            ->call('addPaymentRow')
            ->set('payments.1.method', 'kartu_non_lokal')
            ->call('fillRemaining', 1)
            ->assertSet('payments.1.amount', 100000)
            ->call('processPayment');

        $trx = Transaction::first();
        $this->assertNotNull($trx);
        $this->assertEquals(180000, $trx->subtotal);
        $this->assertEquals(30000, $trx->discount_amount);
        $this->assertEquals(3000, $trx->tax_amount);
        $this->assertEquals(153000, $trx->total);
        $this->assertSame(3, $this->stockOf($product, $outlet));
    }

    // Pesan validasi per-field berbahasa Indonesia (bukan pesan field lain / bawaan Inggris)
    public function test_product_form_validation_messages_are_field_specific_and_indonesian(): void
    {
        $outlet = $this->makeOutlet();
        $this->loginAt(User::factory()->owner()->create(), $outlet);

        Volt::test('products.create')
            ->call('save')
            ->assertHasErrors(['name', 'category_id', 'sell_price'])
            ->assertSee('Nama produk wajib diisi.')
            ->assertSee('Harga jual wajib diisi.')
            ->assertSee('Kategori wajib dipilih.')
            ->assertDontSee('field is required');

        Volt::test('profile.index')
            ->set('name', '')
            ->call('updateProfile')
            ->assertSee('Nama wajib diisi.');
    }
}
