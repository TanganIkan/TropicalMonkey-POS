<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

/**
 * Smoke test: setiap halaman bisa dibuka tanpa error.
 * Dashboard tidak ikut karena memakai DATE_FORMAT (khusus MySQL).
 */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase, CreatesPosData;

    public function test_all_pages_render_for_owner(): void
    {
        $outlet = $this->makeOutlet();
        $owner = User::factory()->owner()->create();
        $product = $this->makeProduct(['brand_id' => Brand::create(['name' => 'Tropical Monkey'])->id]);
        $variantProduct = $this->makeProduct(['name' => 'Kaos Varian']);
        $variant = $this->makeVariant($variantProduct);
        $this->setStock($product, $outlet, 5);
        $this->setStock($variantProduct, $outlet, 5, $variant);
        $trx = $this->checkout(
            ["{$product->id}-single" => ['name' => $product->name, 'price' => 100000, 'quantity' => 1, 'stock' => 5]],
            $outlet->id, $owner->id, 'tunai', 100000,
        );

        $pages = [
            '/pos',
            '/pos/receipt/' . $trx->id,
            '/products',
            '/products/create',
            "/products/{$product->id}/edit",
            "/products/{$product->id}/add-stock",
            "/products/{$variantProduct->id}/add-stock",
            '/stocks/history',
            '/categories-brands',
            '/profile',
            '/staff',
            '/products/top',
            '/transactions',
            '/stocks/opname',
            '/stocks/transfer',
            '/reports',
            '/reports?tab=cashiers',
            '/reports?tab=inventory',
            '/monitoring',
            '/outlets',
            '/settings',
        ];

        foreach ($pages as $page) {
            $this->loginAt($owner, $outlet)->get($page)->assertOk();
        }
    }

    public function test_checkout_page_renders_with_cart(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();
        $product = $this->makeProduct(['name' => 'Kaos Pisang']);

        $this->loginAt($user, $outlet)
            ->withSession(['pos_active_cart' => ["{$product->id}-single" => ['name' => 'Kaos Pisang', 'price' => 100000, 'quantity' => 2, 'stock' => 5, 'sku' => null, 'image' => null]]])
            ->get('/pos/checkout')
            ->assertOk()
            ->assertSee('Kaos Pisang');
    }

    public function test_checkout_redirects_to_pos_when_cart_items_no_longer_exist(): void
    {
        $outlet = $this->makeOutlet();
        $user = User::factory()->create();

        $this->loginAt($user, $outlet)
            ->withSession(['pos_active_cart' => ['999-single' => ['name' => 'Hantu', 'price' => 1000, 'quantity' => 1, 'stock' => 5, 'sku' => null, 'image' => null]]])
            ->get('/pos/checkout')
            ->assertRedirect('/pos');
    }

    public function test_staff_cannot_open_owner_pages(): void
    {
        $outlet = $this->makeOutlet();
        $staff = User::factory()->create();

        foreach (['/dashboard', '/staff', '/products/top', '/monitoring', '/outlets', '/settings'] as $page) {
            $this->loginAt($staff, $outlet)->get($page)->assertForbidden();
        }
    }
}
