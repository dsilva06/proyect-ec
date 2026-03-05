<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_category_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('ranking_source', 10);
            $table->integer('ranking_value')->nullable();
            $table->timestamp('ranking_updated_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'ranking_source'], 'pcr_user_cat_source_uq');
            $table->index(['category_id', 'ranking_source', 'ranking_value'], 'pcr_cat_source_rank_idx');
        });

        $rows = DB::table('registration_rankings as rr')
            ->join('tournament_categories as tc', 'tc.id', '=', 'rr.tournament_category_id')
            ->selectRaw('rr.user_id, tc.category_id, rr.ranking_source, MIN(rr.ranking_value) as ranking_value, MAX(rr.updated_at) as ranking_updated_at')
            ->whereNotNull('rr.user_id')
            ->whereNotNull('rr.ranking_value')
            ->whereNotNull('rr.ranking_source')
            ->groupBy('rr.user_id', 'tc.category_id', 'rr.ranking_source')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $payload = $rows->map(function ($row) {
            $now = now();

            return [
                'user_id' => (int) $row->user_id,
                'category_id' => (int) $row->category_id,
                'ranking_source' => strtoupper((string) $row->ranking_source),
                'ranking_value' => $row->ranking_value !== null ? (int) $row->ranking_value : null,
                'ranking_updated_at' => $row->ranking_updated_at,
                'updated_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();

        DB::table('player_category_rankings')->upsert(
            $payload,
            ['user_id', 'category_id', 'ranking_source'],
            ['ranking_value', 'ranking_updated_at', 'updated_at']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('player_category_rankings');
    }
};

