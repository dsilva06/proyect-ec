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
            'document_type' => 'DNI',
            'document_number' => '12345678Z',
            'dni' => '12345678Z',
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
        $this->assertArrayNotHasKey('document_type', $payload['player_profile']);
        $this->assertArrayNotHasKey('document_number', $payload['player_profile']);
        $this->assertArrayNotHasKey('document', $payload['player_profile']);
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
            'document_type' => 'NIE',
            'document_number' => 'X7654321L',
            'dni' => 'X7654321L',
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
        $this->assertSame('X7654321L', $payload['player_profile']['dni']);
        $this->assertSame('NIE', $payload['player_profile']['document_type']);
        $this->assertSame('X7654321L', $payload['player_profile']['document_number']);
        $this->assertSame([
            'type' => 'NIE',
            'number' => 'X7654321L',
        ], $payload['player_profile']['document']);
    }
}
