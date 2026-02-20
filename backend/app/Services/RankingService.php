<?php

namespace App\Services;

use App\Models\PlayerProfile;
use App\Models\User;

class RankingService
{
    public function getProfile(User $user): PlayerProfile
    {
        return PlayerProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            $this->buildProfileDefaults($user)
        );
    }

    public function updateRanking(User $user, ?int $value, ?string $source): PlayerProfile
    {
        $profile = $this->getProfile($user);

        $profile->ranking_value = $value;
        $profile->ranking_source = $source ?: 'NONE';
        $profile->ranking_updated_at = $value ? now() : null;
        $profile->save();

        return $profile;
    }

    public function getRankingValue(User $user): ?int
    {
        $profile = $this->getProfile($user);

        return $profile->ranking_value !== null ? (int) $profile->ranking_value : null;
    }

    public function getRankingSource(User $user): ?string
    {
        $profile = $this->getProfile($user);

        return $profile->ranking_source;
    }

    private function buildProfileDefaults(User $user): array
    {
        $name = trim((string) $user->name);
        [$firstName, $lastName] = array_pad(preg_split('/\s+/', $name, 2), 2, null);

        return [
            'first_name' => $firstName ?: 'Player',
            'last_name' => $lastName ?: 'Profile',
            'province_state' => 'Unknown',
            'ranking_source' => 'NONE',
            'ranking_value' => null,
            'ranking_updated_at' => null,
        ];
    }
}
