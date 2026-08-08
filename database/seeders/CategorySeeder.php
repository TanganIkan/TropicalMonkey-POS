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
        $categories = [
            'Muscle tee (MT)',
            'Tshirt (TS)',
            'Shirt',
            'Tshirt ladies crop (TL)',
            'Muscle Ladies (MC)',
            'Bikini',
            'Dress',
            'Long pants',
            'Short pants (SP)',
            'Skirt',
            'Top women',
            'Bottom women',
            'Topi',
            'Stiker',
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate([
                'name' => $categoryName
            ]);
        }
    }
}