<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_rankings', function (Blueprint $table) {
            $table->dropUnique('reg_rank_tcat_rank_uq');
        });
    }

    public function down(): void
    {
        Schema::table('registration_rankings', function (Blueprint $table) {
            $table->unique(['tournament_category_id', 'ranking_value'], 'reg_rank_tcat_rank_uq');
        });
    }
};
