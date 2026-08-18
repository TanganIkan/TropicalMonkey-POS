<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        $products = Product::all();

        foreach ($products as $product) {
            foreach ($outlets as $outlet) {
                Stock::create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'outlet_id' => $outlet->id,
                    'quantity' => 20,
                ]);
            }
        }
    }
}