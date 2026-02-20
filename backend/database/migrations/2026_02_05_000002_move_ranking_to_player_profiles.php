<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')
            ->select('id', 'name', 'role', 'ranking_source', 'ranking_value', 'ranking_verified_at')
            ->get();

        foreach ($users as $user) {
            if ($user->role !== 'player') {
                continue;
            }

            $name = trim((string) $user->name);
            [$firstName, $lastName] = array_pad(preg_split('/\s+/', $name, 2), 2, null);
            $firstName = $firstName ?: 'Player';
            $lastName = $lastName ?: 'Profile';

            DB::table('player_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'province_state' => 'Unknown',
                    'ranking_source' => $user->ranking_source ?: 'NONE',
                    'ranking_value' => $user->ranking_value,
                    'ranking_updated_at' => $user->ranking_verified_at,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ranking_verified_by']);
            $table->dropIndex(['ranking_value']);
            $table->dropColumn([
                'ranking_source',
                'ranking_value',
                'ranking_verified_at',
                'ranking_verified_by',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ranking_source')->nullable()->after('last_login_at');
            $table->integer('ranking_value')->nullable()->after('ranking_source');
            $table->timestamp('ranking_verified_at')->nullable()->after('ranking_value');
            $table->foreignId('ranking_verified_by')
                ->nullable()
                ->after('ranking_verified_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('ranking_value');
        });

        $profiles = DB::table('player_profiles')
            ->select('user_id', 'ranking_source', 'ranking_value', 'ranking_updated_at')
            ->get();

        foreach ($profiles as $profile) {
            DB::table('users')
                ->where('id', $profile->user_id)
                ->update([
                    'ranking_source' => $profile->ranking_source,
                    'ranking_value' => $profile->ranking_value,
                    'ranking_verified_at' => $profile->ranking_updated_at,
                ]);
        }
    }
};
