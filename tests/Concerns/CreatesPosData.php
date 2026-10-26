<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\ShiftService;

trait CreatesPosData
{
    protected function makeOutlet(string $name = 'Tropical Monkey'): Outlet
    {
        return Outlet::create(['name' => $name, 'is_active' => true]);
    }

    protected function makeProduct(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['name' => 'Kaos']);

        return Product::create($attributes + [
            'category_id' => $category->id,
            'name' => 'Kaos Monyet',
            'sku' => 'KAO-MON-' . fake()->unique()->numberBetween(1000, 9999),
            'barcode' => (string) fake()->unique()->ean13(),
            'cost_price' => 50000,
            'sell_price' => 100000,
            'has_variants' => false,
            'is_active' => true,
        ]);
    }

    protected function makeVariant(Product $product, string $size = 'M', string $color = 'Black'): ProductVariant
    {
        $product->update(['has_variants' => true]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => "{$product->sku}-{$size}-{$color}",
            'barcode' => (string) fake()->unique()->ean13(),
            'size' => $size,
            'color' => $color,
        ]);
    }

    protected function setStock(Product $product, Outlet $outlet, int $quantity, ?ProductVariant $variant = null): Stock
    {
        return Stock::updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => $variant?->id, 'outlet_id' => $outlet->id],
            ['quantity' => $quantity],
        );
    }

    protected function stockOf(Product $product, Outlet $outlet, ?ProductVariant $variant = null): int
    {
        return (int) Stock::where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->where('outlet_id', $outlet->id)
            ->value('quantity');
    }

    /**
     * Pastikan user punya shift terbuka di outlet ini (shift di outlet lain ditutup dulu).
     */
    protected function openShiftFor(User|int $user, Outlet|int $outlet): Shift
    {
        $user = $user instanceof User ? $user : User::find($user);
        $outletId = $outlet instanceof Outlet ? $outlet->id : $outlet;
        $shifts = app(ShiftService::class);

        if ($current = $shifts->current($user)) {
            if ($current->outlet_id == $outletId) {
                return $current;
            }
            $current->update(['closed_at' => now()]);
        }

        return Shift::create(['user_id' => $user->id, 'outlet_id' => $outletId, 'opened_at' => now(), 'opening_cash' => 0]);
    }

    /**
     * Jalankan checkout dengan satu metode bayar (shift dibuka otomatis).
     */
    protected function checkout(array $cart, int $outletId, int $userId, string $method = 'qris', $cash = null, ?array $orderDiscount = null): Transaction
    {
        $this->openShiftFor($userId, $outletId);

        return app(CheckoutService::class)->process($cart, $outletId, $userId, [['method' => $method, 'cash_received' => $cash]], $orderDiscount);
    }

    protected function loginAt(User $user, Outlet $outlet): static
    {
        return $this->actingAs($user)->withSession(['current_outlet_id' => $outlet->id]);
    }
}
