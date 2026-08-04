<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Owner Utama',
            'username' => 'owner',
            'email' => 'owner@gmail.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        User::create([
            'name' => 'Elga',
            'username' => 'elga',
            'email' => 'elga@gmail.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);
    }
}