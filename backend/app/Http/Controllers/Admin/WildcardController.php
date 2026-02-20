<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\WildcardInvitationResource;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\TeamMember;
use App\Models\TournamentCategory;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\StatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WildcardController extends Controller
{
    public function index(Request $request)
    {
        $query = Invitation::query()
            ->where('purpose', 'wildcard')
            ->with(['status', 'tournamentCategory.category', 'tournamentCategory.tournament.status'])
            ->orderByDesc('created_at');

        if ($request->filled('tournament_category_id')) {
            $query->where('tournament_category_id', $request->query('tournament_category_id'));
        }

        return WildcardInvitationResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tournament_category_id' => ['required', 'exists:tournament_categories,id'],
            'mode' => ['required', 'in:link,manual'],
            'email' => ['required', 'email', 'max:255'],
            'player_name' => ['nullable', 'string', 'max:255'],
            'partner_email' => ['nullable', 'email', 'max:255'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'wildcard_fee_waived' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if ($data['mode'] === 'link') {
            $invite = Invitation::create([
                'tournament_category_id' => $data['tournament_category_id'],
                'purpose' => 'wildcard',
                'email' => $data['email'],
                'partner_email' => $data['partner_email'] ?? null,
                'partner_name' => $data['partner_name'] ?? null,
                'wildcard_fee_waived' => (bool) ($data['wildcard_fee_waived'] ?? false),
                'status_id' => app(StatusService::class)->resolveStatusId('invitation', 'pending'),
                'token' => Str::uuid()->toString(),
                'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            ]);

            $invite->load(['status', 'tournamentCategory.category', 'tournamentCategory.tournament.status']);

            return new WildcardInvitationResource($invite);
        }

        $user = $this->findOrCreateUser($data['email'], $data['player_name'] ?? null);
        $team = $this->createTeam($user, $data['partner_email'] ?? null);

        $invite = Invitation::create([
            'tournament_category_id' => $data['tournament_category_id'],
            'purpose' => 'wildcard',
            'email' => $data['email'],
            'partner_email' => $data['partner_email'] ?? null,
            'partner_name' => $data['partner_name'] ?? null,
            'wildcard_fee_waived' => (bool) ($data['wildcard_fee_waived'] ?? false),
            'status_id' => app(StatusService::class)->resolveStatusId('invitation', 'accepted'),
            'token' => Str::uuid()->toString(),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        $registration = app(RegistrationService::class)->create(
            $user,
            $team->id,
            (int) $data['tournament_category_id'],
            [],
            true,
            (bool) ($data['wildcard_fee_waived'] ?? false),
            $invite->id
        );

        $registration->load([
            'status',
            'team.users.playerProfile',
            'tournamentCategory.tournament.status',
            'tournamentCategory.category',
        ]);

        return new RegistrationResource($registration);
    }

    private function findOrCreateUser(string $email, ?string $name): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return $user;
        }

        return User::create([
            'name' => $name ?: $email,
            'email' => $email,
            'password_hash' => Hash::make(Str::random(16)),
            'role' => 'player',
            'is_active' => true,
        ]);
    }

    private function createTeam(User $user, ?string $partnerEmail): Team
    {
        $team = Team::create([
            'display_name' => $user->name ?: 'Equipo',
            'created_by' => $user->id,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'slot' => 1,
        ]);

        if ($partnerEmail) {
            $partnerUser = User::query()->where('email', $partnerEmail)->first();
            if ($partnerUser) {
                TeamMember::create([
                    'team_id' => $team->id,
                    'user_id' => $partnerUser->id,
                    'slot' => 2,
                ]);
                $team->display_name = trim(($user->name ?: 'Jugador').' / '.($partnerUser->name ?: 'Jugador'));
                $team->save();
            } else {
                TeamInvite::create([
                    'team_id' => $team->id,
                    'invited_email' => $partnerEmail,
                    'token' => Str::uuid()->toString(),
                    'status_id' => app(StatusService::class)->resolveStatusId('team_invite', 'sent'),
                    'expires_at' => now()->addDays(7),
                ]);
            }
        }

        return $team->fresh(['users', 'invites']);
    }
}
