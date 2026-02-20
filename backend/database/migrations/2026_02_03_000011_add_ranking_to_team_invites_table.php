<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_invites', function (Blueprint $table) {
            $table->integer('invited_ranking_value')->nullable()->after('invited_user_id');
            $table->string('invited_ranking_source')->nullable()->after('invited_ranking_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_invites', function (Blueprint $table) {
            $table->dropColumn(['invited_ranking_value', 'invited_ranking_source']);
        });
    }
};
