<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Kaos Polos Hitam', 'category' => 'Kaos', 'brand' => 'Velvet Basics', 'sku' => 'KOS-HTM-M', 'barcode' => '8991111111111', 'cost_price' => 35000, 'sell_price' => 65000],
            ['name' => 'Kaos Polos Putih', 'category' => 'Kaos', 'brand' => 'Velvet Basics', 'sku' => 'KOS-PTH-M', 'barcode' => '8991111111112', 'cost_price' => 35000, 'sell_price' => 65000],
            ['name' => 'Celana Jeans Slim Fit', 'category' => 'Celana', 'brand' => 'Denim Co', 'sku' => 'CLN-JNS-32', 'barcode' => '8991111111113', 'cost_price' => 120000, 'sell_price' => 220000],
        ];

        foreach ($products as $item) {
            $category = Category::firstOrCreate(['name' => $item['category']]);

            Product::create([
                'category_id' => $category->id,
                'name' => $item['name'],
                'brand' => $item['brand'],
                'sku' => $item['sku'],
                'barcode' => $item['barcode'],
                'cost_price' => $item['cost_price'],
                'sell_price' => $item['sell_price'],
                'is_active' => true,
            ]);
        }
    }
}