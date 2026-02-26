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
        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('tournament_category_id')
                ->nullable()
                ->after('id')
                ->constrained('tournament_categories')
                ->cascadeOnDelete();
        });

        // SQLite does not support this JOIN-update pattern reliably.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE matches
                SET tournament_category_id = (
                    SELECT brackets.tournament_category_id
                    FROM brackets
                    WHERE brackets.id = matches.bracket_id
                )
                WHERE bracket_id IS NOT NULL
            ');
        } else {
            DB::table('matches')
                ->join('brackets', 'matches.bracket_id', '=', 'brackets.id')
                ->update([
                    'matches.tournament_category_id' => DB::raw('brackets.tournament_category_id'),
                ]);
        }

        Schema::table('matches', function (Blueprint $table) {
            $table->index(['tournament_category_id']);
            $table->index(['tournament_category_id', 'round_number']);
            $table->index(['bracket_id', 'round_number', 'match_number']);
            $table->dropIndex(['bracket_id', 'round_number']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE matches MODIFY tournament_category_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE matches ALTER COLUMN tournament_category_id SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['tournament_category_id']);
            $table->dropIndex(['tournament_category_id', 'round_number']);
            $table->dropIndex(['bracket_id', 'round_number', 'match_number']);
            $table->index(['bracket_id', 'round_number']);
            $table->dropForeign(['tournament_category_id']);
            $table->dropColumn('tournament_category_id');
        });
    }
};
