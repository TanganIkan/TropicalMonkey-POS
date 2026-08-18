<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        Outlet::updateOrCreate(
            ['name' => 'Tropical Monkey'],
            [
                'address' => 'Jl. Monkey Forest, Ubud, Kecamatan Ubud, Kabupaten Gianyar, Bali 80571',
                'phone' => '082339194644',
                'is_active' => true,
            ]
        );

        Outlet::updateOrCreate(
            ['name' => 'Tropisoul'],
            [
                'address' => 'Jl. Arjuna, Ubud, Kecamatan Ubud, Kabupaten Gianyar, Bali 80571',
                'phone' => '082145263950',
                'is_active' => true,
            ]
        );
    }
}