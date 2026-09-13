<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [

            [
                'name' => 'Shoes',
                'slug' => 'shoes',
                'icon' => 'bi bi-sneaker'
            ],

            [
                'name' => 'Shirt',
                'slug' => 'shirt',
                'icon' => 'bi bi-tshirt'
            ],

            [
                'name' => 'Pant',
                'slug' => 'pant',
                'icon' => 'bi bi-layers'
            ],

            [
                'name' => 'Bag',
                'slug' => 'bag',
                'icon' => 'bi bi-bag'
            ],

            [
                'name' => 'Accessory',
                'slug' => 'accessory',
                'icon' => 'bi bi-gem'
            ],

            [
                'name' => 'Dress',
                'slug' => 'dress',
                'icon' => 'bi bi-flower1'
            ],

            [
                'name' => 'Women',
                'slug' => 'women',
                'icon' => 'bi bi-gender-female'
            ],

            [
                'name' => 'Men',
                'slug' => 'men',
                'icon' => 'bi bi-gender-male'
            ]

        ];

        foreach ($categories as $category) {

            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
