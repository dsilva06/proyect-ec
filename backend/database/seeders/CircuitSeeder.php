<?php

namespace Database\Seeders;

use App\Models\Circuit;
use Illuminate\Database\Seeder;

class CircuitSeeder extends Seeder
{
    public function run(): void
    {
        Circuit::updateOrCreate(
            ['name' => 'Circuito Principal'],
            ['description' => 'Circuito por defecto para torneos.']
        );
    }
}
