<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        Outlet::create([
            'name' => 'Tropical Monkey',
            'address' => 'Alamat Tropical Monkey', // sesuaikan
            'phone' => '081234567890', // sesuaikan
            'is_active' => true,
        ]);

        Outlet::create([
            'name' => 'Tropisoul',
            'address' => 'Alamat Tropisoul', // sesuaikan
            'phone' => '081234567891', // sesuaikan
            'is_active' => true,
        ]);
    }
}