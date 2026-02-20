<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\PlayerProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@proyect-ec.test'],
            [
                'name' => 'Admin User',
                'password_hash' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'player@proyect-ec.test'],
            [
                'name' => 'Player User',
                'password_hash' => Hash::make('password'),
                'role' => 'player',
                'is_active' => true,
            ]
        );

        $player = User::where('email', 'player@proyect-ec.test')->first();
        if ($player) {
            PlayerProfile::updateOrCreate(
                ['user_id' => $player->id],
                [
                    'first_name' => 'Player',
                    'last_name' => 'User',
                    'province_state' => 'Unknown',
                    'ranking_source' => 'NONE',
                    'ranking_value' => null,
                    'ranking_updated_at' => null,
                ]
            );
        }
    }
}
