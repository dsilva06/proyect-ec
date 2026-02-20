<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::query()->delete();

        $categories = [
            [
                'name' => 'Masculino Open',
                'display_name' => 'Masculino Open',
                'group_code' => 'masculino',
                'level_code' => 'open',
                'sort_order' => 1,
            ],
            [
                'name' => 'Masculino 1era',
                'display_name' => 'Masculino 1era',
                'group_code' => 'masculino',
                'level_code' => 'primera',
                'sort_order' => 2,
            ],
            [
                'name' => 'Masculino 2da',
                'display_name' => 'Masculino 2da',
                'group_code' => 'masculino',
                'level_code' => 'segunda',
                'sort_order' => 3,
            ],
            [
                'name' => 'Femenino Open',
                'display_name' => 'Femenino Open',
                'group_code' => 'femenino',
                'level_code' => 'open',
                'sort_order' => 4,
            ],
            [
                'name' => 'Femenino 1era',
                'display_name' => 'Femenino 1era',
                'group_code' => 'femenino',
                'level_code' => 'primera',
                'sort_order' => 5,
            ],
            [
                'name' => 'Femenino 2da',
                'display_name' => 'Femenino 2da',
                'group_code' => 'femenino',
                'level_code' => 'segunda',
                'sort_order' => 6,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
