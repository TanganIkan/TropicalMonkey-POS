<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\DB; // <-- Wajib tambahkan ini untuk mematikan relasi sementara

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Category::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $categories = [
            'BAG',
            'BIKINI BOTTOM',
            'BIKINI TOP',
            'BOARDSHORT',
            'BRA',
            'CAP',
            'CROP',
            'CROP TEE',
            'GIRL SHIRT',
            'GIRL TANKTOP',
            'LONG PANT',
            'LONG PANTS',
            'MUSCLE',
            'MUSCLE CROP',
            'MUSCLE TEE',
            'PANT',
            'RASH GUARD',
            'SET',
            'SET LONG',
            'SET SHORT',
            'SHIRT',
            'SHORT',
            'SHORT PANT',
            'SHORTS',
            'SKIRT',
            'SNAPBACK',
            'STICKER',
            'T-SHIRT',
            'T-SHIRT GIRL',
            'T-SHIRT KIDS',
            'TOTE BAG',
            'TRUCKER',
            'WOMEN SHORT',
        ];

        foreach ($categories as $categoryName) {
            Category::create([
                'name' => $categoryName
            ]);
        }
    }
}