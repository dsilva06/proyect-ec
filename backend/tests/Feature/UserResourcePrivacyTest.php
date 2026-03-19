<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourcePrivacyTest extends TestCase
{
    public function test_non_admin_viewer_cannot_see_sensitive_fields_of_another_user(): void
    {
        $viewer = new User();
        $viewer->forceFill([
            'id' => 1,
            'role' => 'player',
        ]);

        $subject = new User();
        $subject->forceFill([
            'id' => 2,
            'name' => 'Another Player',
            'email' => 'other@example.com',
            'phone' => '+584121234567',
            'role' => 'player',
            'is_active' => true,
        ]);
        $subject->setRelation('playerProfile', new PlayerProfile([
            'first_name' => 'Another',
            'last_name' => 'Player',
            'dni' => 'V-12345678',
            'province_state' => 'Caracas',
            'ranking_source' => 'FEP',
            'ranking_value' => 100,
        ]));

        $request = Request::create('/api/player/brackets', 'GET');
        $request->setUserResolver(fn () => $viewer);

        $payload = (new UserResource($subject))->toArray($request);

        $this->assertNull($payload['email']);
        $this->assertNull($payload['phone']);
        $this->assertArrayHasKey('player_profile', $payload);
        $this->assertArrayNotHasKey('dni', $payload['player_profile']);
    }

    public function test_user_can_see_own_sensitive_fields(): void
    {
        $viewer = new User();
        $viewer->forceFill([
            'id' => 5,
            'role' => 'player',
        ]);

        $subject = new User();
        $subject->forceFill([
            'id' => 5,
            'name' => 'Same User',
            'email' => 'same@example.com',
            'phone' => '+584121112233',
            'role' => 'player',
            'is_active' => true,
        ]);
        $subject->setRelation('playerProfile', new PlayerProfile([
            'first_name' => 'Same',
            'last_name' => 'User',
            'dni' => 'V-87654321',
            'province_state' => 'Lara',
            'ranking_source' => 'FIP',
            'ranking_value' => 50,
        ]));

        $request = Request::create('/api/player/me', 'GET');
        $request->setUserResolver(fn () => $viewer);

        $payload = (new UserResource($subject))->toArray($request);

        $this->assertSame('same@example.com', $payload['email']);
        $this->assertSame('+584121112233', $payload['phone']);
        $this->assertArrayHasKey('dni', $payload['player_profile']);
        $this->assertSame('V-87654321', $payload['player_profile']['dni']);
    }
}
