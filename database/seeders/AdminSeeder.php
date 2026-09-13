<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Punlue',
            'email' => 'punlue@ecommerce.com',
            'phone' => '070644318',
            'address' => 'Phnom Penh, Cambodia',
            'is_admin' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password123'), // Change this!
        ]);
    }
}
