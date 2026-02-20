<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(StatusSeeder::class);
        $this->call(CircuitSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(UserSeeder::class);

        if (env('SEED_DEMO_TOURNAMENT')) {
            $this->call(DemoTournamentSeeder::class);
        }
    }
}
