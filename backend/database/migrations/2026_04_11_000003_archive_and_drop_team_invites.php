<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEAM_INVITE_ENTITY_TYPE = 'App\\Models\\TeamInvite';

    public function up(): void
    {
        Schema::createIfNotExists('archived_team_invites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_team_invite_id')->unique();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('invited_email')->nullable();
            $table->string('invited_phone')->nullable();
            $table->unsignedBigInteger('invited_user_id')->nullable();
            $table->integer('invited_ranking_value')->nullable();
            $table->string('invited_ranking_source')->nullable();
            $table->string('token')->nullable();
            $table->string('status_code')->nullable();
            $table->string('status_label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_last_error')->nullable();
            $table->unsignedInteger('email_attempts')->default(0);
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('original_updated_at')->nullable();
            $table->timestamp('archived_at');

            $table->index(['team_id']);
            $table->index(['invited_email']);
        });

        Schema::createIfNotExists('archived_team_invite_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_status_history_id')->unique();
            $table->unsignedBigInteger('legacy_team_invite_id')->nullable();
            $table->foreignId('archived_team_invite_id')->nullable()->constrained('archived_team_invites')->nullOnDelete();
            $table->string('module');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('from_status_code')->nullable();
            $table->string('to_status_code');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamp('archived_at');

            $table->index(['legacy_team_invite_id']);
            $table->index(['module', 'entity_type', 'entity_id']);
        });

        $archivedAt = now();

        if (Schema::hasTable('team_invites')) {
            $inviteRows = DB::table('team_invites')
                ->leftJoin('statuses as invite_statuses', 'team_invites.status_id', '=', 'invite_statuses.id')
                ->select([
                    'team_invites.id',
                    'team_invites.team_id',
                    'team_invites.invited_email',
                    'team_invites.invited_phone',
                    'team_invites.invited_user_id',
                    'team_invites.invited_ranking_value',
                    'team_invites.invited_ranking_source',
                    'team_invites.token',
                    'invite_statuses.code as status_code',
                    'invite_statuses.label as status_label',
                    'team_invites.expires_at',
                    'team_invites.email_sent_at',
                    'team_invites.email_last_error',
                    'team_invites.email_attempts',
                    'team_invites.created_at',
                    'team_invites.updated_at',
                ])
                ->orderBy('team_invites.id')
                ->get();

            foreach ($inviteRows->chunk(200) as $chunk) {
                DB::table('archived_team_invites')->insert(
                    $chunk->map(fn ($invite) => [
                        'legacy_team_invite_id' => $invite->id,
                        'team_id' => $invite->team_id,
                        'invited_email' => $invite->invited_email,
                        'invited_phone' => $invite->invited_phone,
                        'invited_user_id' => $invite->invited_user_id,
                        'invited_ranking_value' => $invite->invited_ranking_value,
                        'invited_ranking_source' => $invite->invited_ranking_source,
                        'token' => $invite->token,
                        'status_code' => $invite->status_code,
                        'status_label' => $invite->status_label,
                        'expires_at' => $invite->expires_at,
                        'email_sent_at' => $invite->email_sent_at,
                        'email_last_error' => $invite->email_last_error,
                        'email_attempts' => $invite->email_attempts ?? 0,
                        'original_created_at' => $invite->created_at,
                        'original_updated_at' => $invite->updated_at,
                        'archived_at' => $archivedAt,
                    ])->all()
                );
            }

            $archiveIdsByLegacyId = DB::table('archived_team_invites')
                ->pluck('id', 'legacy_team_invite_id');

            $historyRows = DB::table('status_history')
                ->leftJoin('statuses as from_statuses', 'status_history.from_status_id', '=', 'from_statuses.id')
                ->leftJoin('statuses as to_statuses', 'status_history.to_status_id', '=', 'to_statuses.id')
                ->where(function ($query) {
                    $query->where('status_history.module', 'team_invite')
                        ->orWhere('status_history.entity_type', self::TEAM_INVITE_ENTITY_TYPE);
                })
                ->select([
                    'status_history.id',
                    'status_history.module',
                    'status_history.entity_type',
                    'status_history.entity_id',
                    'status_history.changed_by',
                    'status_history.reason',
                    'status_history.meta',
                    'status_history.created_at',
                    'from_statuses.code as from_status_code',
                    'to_statuses.code as to_status_code',
                ])
                ->orderBy('status_history.id')
                ->get();

            foreach ($historyRows->chunk(200) as $chunk) {
                DB::table('archived_team_invite_status_history')->insert(
                    $chunk->map(fn ($history) => [
                        'legacy_status_history_id' => $history->id,
                        'legacy_team_invite_id' => $history->entity_type === self::TEAM_INVITE_ENTITY_TYPE ? $history->entity_id : null,
                        'archived_team_invite_id' => $history->entity_type === self::TEAM_INVITE_ENTITY_TYPE
                            ? ($archiveIdsByLegacyId[$history->entity_id] ?? null)
                            : null,
                        'module' => $history->module,
                        'entity_type' => $history->entity_type,
                        'entity_id' => $history->entity_id,
                        'from_status_code' => $history->from_status_code,
                        'to_status_code' => $history->to_status_code,
                        'changed_by' => $history->changed_by,
                        'reason' => $history->reason,
                        'meta' => $history->meta,
                        'original_created_at' => $history->created_at,
                        'archived_at' => $archivedAt,
                    ])->all()
                );
            }
        }

        $pendingPartnerAcceptanceTeamStatusId = $this->statusId('team', 'pending_partner_acceptance');
        $confirmedTeamStatusId = $this->statusId('team', 'confirmed');
        if ($pendingPartnerAcceptanceTeamStatusId && $confirmedTeamStatusId) {
            DB::table('teams')
                ->where('status_id', $pendingPartnerAcceptanceTeamStatusId)
                ->update([
                    'status_id' => $confirmedTeamStatusId,
                    'updated_at' => now(),
                ]);
        }

        $awaitingPartnerAcceptanceRegistrationStatusId = $this->statusId('registration', 'awaiting_partner_acceptance');
        $paidRegistrationStatusId = $this->statusId('registration', 'paid');
        if ($awaitingPartnerAcceptanceRegistrationStatusId && $paidRegistrationStatusId) {
            DB::table('registrations')
                ->where('status_id', $awaitingPartnerAcceptanceRegistrationStatusId)
                ->update([
                    'status_id' => $paidRegistrationStatusId,
                    'accepted_at' => DB::raw('COALESCE(accepted_at, CURRENT_TIMESTAMP)'),
                    'payment_due_at' => null,
                    'updated_at' => now(),
                ]);
        }

        $removedStatusIds = DB::table('statuses')
            ->where(function ($query) {
                $query->where('module', 'team_invite')
                    ->orWhere(function ($innerQuery) {
                        $innerQuery->where('module', 'registration')
                            ->where('code', 'awaiting_partner_acceptance');
                    })
                    ->orWhere(function ($innerQuery) {
                        $innerQuery->where('module', 'team')
                            ->where('code', 'pending_partner_acceptance');
                    });
            })
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if ($removedStatusIds !== []) {
            DB::table('status_history')
                ->where(function ($query) use ($removedStatusIds) {
                    $query->whereIn('from_status_id', $removedStatusIds)
                        ->orWhereIn('to_status_id', $removedStatusIds);
                })
                ->delete();

            DB::table('status_transitions')
                ->where(function ($query) use ($removedStatusIds) {
                    $query->whereIn('from_status_id', $removedStatusIds)
                        ->orWhereIn('to_status_id', $removedStatusIds);
                })
                ->delete();
        }

        if (Schema::hasTable('team_invites')) {
            DB::table('team_invites')->delete();
            Schema::drop('team_invites');
        }

        if ($removedStatusIds !== []) {
            DB::table('statuses')->whereIn('id', $removedStatusIds)->delete();
        }
    }

    public function down(): void
    {
        $this->restoreInviteStatuses();

        Schema::create('team_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('invited_email')->nullable();
            $table->string('invited_phone')->nullable();
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('invited_ranking_value')->nullable();
            $table->string('invited_ranking_source')->nullable();
            $table->string('token')->unique();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_last_error')->nullable();
            $table->unsignedInteger('email_attempts')->default(0);
            $table->timestamps();

            $table->index(['team_id']);
            $table->index(['status_id', 'invited_email']);
        });

        if (Schema::hasTable('archived_team_invites')) {
            $teamInviteStatusIds = DB::table('statuses')
                ->where('module', 'team_invite')
                ->pluck('id', 'code');

            $archivedInvites = DB::table('archived_team_invites')
                ->orderBy('legacy_team_invite_id')
                ->get();

            foreach ($archivedInvites->chunk(200) as $chunk) {
                DB::table('team_invites')->insert(
                    $chunk->map(fn ($invite) => [
                        'id' => $invite->legacy_team_invite_id,
                        'team_id' => $invite->team_id,
                        'invited_email' => $invite->invited_email,
                        'invited_phone' => $invite->invited_phone,
                        'invited_user_id' => $invite->invited_user_id,
                        'invited_ranking_value' => $invite->invited_ranking_value,
                        'invited_ranking_source' => $invite->invited_ranking_source,
                        'token' => $invite->token,
                        'status_id' => $teamInviteStatusIds[$invite->status_code] ?? null,
                        'expires_at' => $invite->expires_at,
                        'email_sent_at' => $invite->email_sent_at,
                        'email_last_error' => $invite->email_last_error,
                        'email_attempts' => $invite->email_attempts ?? 0,
                        'created_at' => $invite->original_created_at,
                        'updated_at' => $invite->original_updated_at,
                    ])->filter(fn ($invite) => $invite['status_id'] !== null)->values()->all()
                );
            }
        }

        if (Schema::hasTable('archived_team_invite_status_history')) {
            $teamInviteStatusIds = DB::table('statuses')
                ->where('module', 'team_invite')
                ->pluck('id', 'code');

            $historyRows = DB::table('archived_team_invite_status_history')
                ->orderBy('legacy_status_history_id')
                ->get();

            foreach ($historyRows->chunk(200) as $chunk) {
                DB::table('status_history')->insert(
                    $chunk->map(fn ($history) => [
                        'id' => $history->legacy_status_history_id,
                        'module' => $history->module,
                        'entity_type' => $history->entity_type,
                        'entity_id' => $history->entity_id,
                        'from_status_id' => $history->from_status_code
                            ? ($teamInviteStatusIds[$history->from_status_code] ?? null)
                            : null,
                        'to_status_id' => $teamInviteStatusIds[$history->to_status_code] ?? null,
                        'changed_by' => $history->changed_by,
                        'reason' => $history->reason,
                        'meta' => $history->meta,
                        'created_at' => $history->original_created_at,
                    ])->filter(fn ($history) => $history['to_status_id'] !== null)->values()->all()
                );
            }
        }

        Schema::dropIfExists('archived_team_invite_status_history');
        Schema::dropIfExists('archived_team_invites');
    }

    private function statusId(string $module, string $code): ?int
    {
        $statusId = DB::table('statuses')
            ->where('module', $module)
            ->where('code', $code)
            ->value('id');

        return $statusId ? (int) $statusId : null;
    }

    private function restoreInviteStatuses(): void
    {
        $definitions = [
            ['module' => 'registration', 'code' => 'awaiting_partner_acceptance', 'label' => 'Awaiting Partner Acceptance', 'terminal' => false, 'sort_order' => 5],
            ['module' => 'team_invite', 'code' => 'pending', 'label' => 'Pending', 'terminal' => false, 'sort_order' => 1],
            ['module' => 'team_invite', 'code' => 'accepted', 'label' => 'Accepted', 'terminal' => true, 'sort_order' => 2],
            ['module' => 'team_invite', 'code' => 'rejected', 'label' => 'Rejected', 'terminal' => true, 'sort_order' => 3],
            ['module' => 'team_invite', 'code' => 'expired', 'label' => 'Expired', 'terminal' => true, 'sort_order' => 4],
            ['module' => 'team_invite', 'code' => 'revoked', 'label' => 'Revoked', 'terminal' => true, 'sort_order' => 5],
            ['module' => 'team', 'code' => 'pending_partner_acceptance', 'label' => 'Pending Partner Acceptance', 'terminal' => false, 'sort_order' => 1],
        ];

        foreach ($definitions as $definition) {
            DB::table('statuses')->updateOrInsert(
                ['module' => $definition['module'], 'code' => $definition['code']],
                [
                    'label' => $definition['label'],
                    'is_terminal' => $definition['terminal'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
};
