<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WildcardInvitationResource;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\StatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WildcardController extends Controller
{
    public function index(Request $request)
    {
        $query = Invitation::query()
            ->where('purpose', 'wildcard')
            ->with([
                'status',
                'user.playerProfile',
                'team.users.playerProfile',
                'tournamentCategory.category',
                'tournamentCategory.tournament.status',
                'wildcardRegistration.status',
                'wildcardRegistration.team.users.playerProfile',
                'wildcardRegistration.rankings.user.playerProfile',
                'wildcardRegistration.rankings.verifier',
                'wildcardRegistration.tournamentCategory.category',
                'wildcardRegistration.tournamentCategory.tournament.status',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('tournament_id')) {
            $tournamentId = (int) $request->query('tournament_id');
            $query->whereHas('tournamentCategory', function ($builder) use ($tournamentId) {
                $builder->where('tournament_id', $tournamentId);
            });
        }

        if ($request->filled('tournament_category_id')) {
            $query->where('tournament_category_id', (int) $request->query('tournament_category_id'));
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', (int) $request->query('status_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('email', 'like', "%{$search}%")
                    ->orWhere('partner_email', 'like', "%{$search}%")
                    ->orWhere('partner_name', 'like', "%{$search}%")
                    ->orWhere('token', 'like', "%{$search}%");
            });
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

            return new WildcardInvitationResource($this->loadWildcard($invite));
        }

        $invite = DB::transaction(function () use ($data) {
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

            app(RegistrationService::class)->create(
                $user,
                $team->id,
                (int) $data['tournament_category_id'],
                [],
                true,
                (bool) ($data['wildcard_fee_waived'] ?? false),
                $invite->id
            );

            return $invite;
        });

        return new WildcardInvitationResource($this->loadWildcard($invite));
    }

    public function update(Request $request, Invitation $wildcard)
    {
        $this->ensureIsWildcard($wildcard);

        $data = $request->validate([
            'tournament_category_id' => ['nullable', 'exists:tournament_categories,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'partner_email' => ['nullable', 'email', 'max:255'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'wildcard_fee_waived' => ['nullable', 'boolean'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $registration = $wildcard->wildcardRegistration()->first();

        if (
            $registration
            && array_key_exists('tournament_category_id', $data)
            && (int) $data['tournament_category_id'] !== (int) $wildcard->tournament_category_id
        ) {
            throw ValidationException::withMessages([
                'tournament_category_id' => 'No puedes cambiar la categoría porque este wildcard ya generó una inscripción.',
            ]);
        }

        if (array_key_exists('status_id', $data) && $data['status_id']) {
            app(StatusService::class)->transition(
                $wildcard,
                'invitation',
                (int) $data['status_id'],
                $request->user()?->id,
                'admin_update'
            );
            unset($data['status_id']);
        }

        if (array_key_exists('expires_at', $data) && empty($data['expires_at'])) {
            $data['expires_at'] = null;
        }

        if (! empty($data)) {
            $wildcard->fill($data);
            $wildcard->save();
        }

        if ($registration && array_key_exists('wildcard_fee_waived', $data)) {
            $registration->wildcard_fee_waived = (bool) $data['wildcard_fee_waived'];
            $registration->save();
        }

        return new WildcardInvitationResource($this->loadWildcard($wildcard));
    }

    public function destroy(Invitation $wildcard)
    {
        $this->ensureIsWildcard($wildcard);

        if ($wildcard->wildcardRegistration()->exists()) {
            throw ValidationException::withMessages([
                'wildcard' => 'Este wildcard ya tiene inscripción asociada y no se puede eliminar.',
            ]);
        }

        $wildcard->delete();

        return response()->noContent();
    }

    private function ensureIsWildcard(Invitation $invitation): void
    {
        if ($invitation->purpose !== 'wildcard') {
            throw ValidationException::withMessages([
                'wildcard' => 'La invitación seleccionada no es de tipo wildcard.',
            ]);
        }
    }

    private function loadWildcard(Invitation $invite): Invitation
    {
        return $invite->fresh([
            'status',
            'user.playerProfile',
            'team.users.playerProfile',
            'tournamentCategory.category',
            'tournamentCategory.tournament.status',
            'wildcardRegistration.status',
            'wildcardRegistration.team.users.playerProfile',
            'wildcardRegistration.rankings.user.playerProfile',
            'wildcardRegistration.rankings.verifier',
            'wildcardRegistration.tournamentCategory.category',
            'wildcardRegistration.tournamentCategory.tournament.status',
        ]);
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
            'status_id' => app(StatusService::class)->resolveStatusId('team', Team::STATUS_CONFIRMED),
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'slot' => 1,
            'role' => TeamMember::ROLE_CAPTAIN,
        ]);

        if ($partnerEmail) {
            $partnerUser = User::query()->where('email', $partnerEmail)->first();
            if ($partnerUser) {
                TeamMember::create([
                    'team_id' => $team->id,
                    'user_id' => $partnerUser->id,
                    'slot' => 2,
                    'role' => TeamMember::ROLE_PARTNER,
                ]);
                $team->display_name = trim(($user->name ?: 'Jugador').' / '.($partnerUser->name ?: 'Jugador'));
                $team->save();
            } else {
                $team->status_id = app(StatusService::class)->resolveStatusId('team', Team::STATUS_PENDING_PARTNER_ACCEPTANCE);
                $team->save();

                TeamInvite::create([
                    'team_id' => $team->id,
                    'invited_email' => $partnerEmail,
                    'token' => Str::uuid()->toString(),
                    'status_id' => app(StatusService::class)->resolveStatusId('team_invite', TeamInvite::STATUS_PENDING),
                    'expires_at' => now()->addDays(7),
                ]);
            }
        }

        return $team->fresh(['users', 'invites']);
    }
}
