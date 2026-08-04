<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar kategori sesuai dengan desain Velvet Retail dan tambahan umum
        $categories = [
            'Apparel',
            'Footwear',
            'Accessories',
            'Lifestyle',
            'Perawatan & Kecantikan',
            'Kebutuhan Sehari-hari'
        ];

        foreach ($categories as $categoryName) {
            Category::create([
                'name' => $categoryName,
            ]);
        }
    }
}