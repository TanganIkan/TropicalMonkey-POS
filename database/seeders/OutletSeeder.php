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
            'address' => 'Jl. Monkey Forest, Ubud, Kecamatan Ubud, Kabupaten Gianyar, Bali 80571', // sesuaikan
            'phone' => '082339194644',
            'is_active' => true,
        ]);

        Outlet::create([
            'name' => 'Tropisoul',
            'address' => 'Jl. Arjuna, Ubud, Kecamatan Ubud, Kabupaten Gianyar, Bali 80571', // sesuaikan
            'phone' => '082145263950',
            'is_active' => true,
        ]);
    }
}