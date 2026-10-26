<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PosException;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    private function cartItem(string $name, int $qty, float $price = 100000, int $stock = 99): array
    {
        return ['name' => $name, 'sku' => null, 'image' => null, 'price' => $price, 'quantity' => $qty, 'stock' => $stock];
    }

    public function test_checkout_creates_transaction_and_deducts_stock(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);

        $trx = $this->checkout(
            ["{$product->id}-single" => $this->cartItem('Kaos Monyet', 2)],
            $outlet->id, $user->id, 'tunai', 250000,
        );

        $this->assertSame(3, $this->stockOf($product, $outlet));
        $this->assertEquals(200000, $trx->total);
        $this->assertEquals(50000, $trx->change_amount);
        $this->assertDatabaseHas('transaction_items', ['transaction_id' => $trx->id, 'quantity' => 2]);
        $this->assertDatabaseHas('stock_histories', ['type' => 'sale', 'quantity' => 2, 'nota_number' => $trx->order_id, 'stock_before' => 5, 'stock_after' => 3]);
    }

    public function test_non_local_card_adds_three_percent(): void
    {
        $calc = app(CheckoutService::class)->calculate(['1-single' => $this->cartItem('A', 1, 100000)], null, [['method' => 'kartu_non_lokal']]);

        $this->assertEquals(3000, $calc['tax']);
        $this->assertEquals(103000, $calc['total']);
    }

    // A1: stok habis duluan (dijual kasir lain) setelah barang masuk keranjang
    public function test_checkout_fails_and_rolls_back_when_stock_ran_out(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $first = $this->makeProduct(['name' => 'Kaos A']);
        $second = $this->makeProduct(['name' => 'Kaos B']);
        $this->setStock($first, $outlet, 5);
        $this->setStock($second, $outlet, 1);

        try {
            $this->checkout([
                "{$first->id}-single" => $this->cartItem('Kaos A', 2),
                "{$second->id}-single" => $this->cartItem('Kaos B', 2),
            ], $outlet->id, $user->id, 'qris');
            $this->fail('Checkout seharusnya ditolak.');
        } catch (InsufficientStockException $e) {
            $this->assertSame(1, $e->available);
        }

        // Item pertama yang sempat dipotong ikut dibatalkan
        $this->assertSame(5, $this->stockOf($first, $outlet));
        $this->assertSame(1, $this->stockOf($second, $outlet));
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, StockHistory::count());
    }

    // A2: tidak ada baris stok sama sekali -> dulu tetap terjual tanpa potong stok
    public function test_checkout_fails_when_stock_row_is_missing(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();

        $this->expectException(InsufficientStockException::class);

        try {
            $this->checkout(
                ["{$product->id}-single" => $this->cartItem('Kaos Monyet', 1)],
                $outlet->id, $user->id, 'qris',
            );
        } finally {
            $this->assertSame(0, Transaction::count());
        }
    }

    public function test_cash_payment_must_cover_total(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 5);

        $this->expectException(PosException::class);
        $this->expectExceptionMessage('masih kurang dari');

        $this->checkout(
            ["{$product->id}-single" => $this->cartItem('Kaos Monyet', 1)],
            $outlet->id, $user->id, 'tunai', 50000,
        );
    }

    public function test_variant_stock_is_deducted_per_variant(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $m = $this->makeVariant($product, 'M');
        $l = $this->makeVariant($product, 'L');
        $this->setStock($product, $outlet, 4, $m);
        $this->setStock($product, $outlet, 4, $l);

        $this->checkout(
            ["{$product->id}-{$m->id}" => $this->cartItem('Kaos (M)', 3)],
            $outlet->id, $user->id, 'qris',
        );

        $this->assertSame(1, $this->stockOf($product, $outlet, $m));
        $this->assertSame(4, $this->stockOf($product, $outlet, $l));
    }

    public function test_cart_rejects_more_than_available_stock(): void
    {
        $outlet = $this->makeOutlet();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 1);
        $carts = app(CartService::class);

        $cart = $carts->add([], $product, null, $outlet->id);
        $this->assertSame(1, $cart["{$product->id}-single"]['quantity']);

        $this->expectException(InsufficientStockException::class);
        $carts->add($cart, $product, null, $outlet->id);
    }

    public function test_pos_flow_through_livewire_components(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->setStock($product, $outlet, 3);
        $this->loginAt($user, $outlet);
        $this->openShiftFor($user, $outlet);

        Volt::test('pos.index')
            ->set('search', $product->barcode)
            ->call('scanOrSearch')
            ->assertSet('cart.' . $product->id . '-single.quantity', 1);

        Volt::test('pos.checkout')
            ->call('selectPayment', 'qris')
            ->call('processPayment')
            ->assertRedirect('/pos/receipt/' . Transaction::first()->id);

        $this->assertSame(2, $this->stockOf($product, $outlet));
        $this->assertEmpty(session('pos_active_cart'));
    }
}
