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
        if (! Schema::hasTable('player_profiles') || Schema::hasColumn('player_profiles', 'dni')) {
            return;
        }

        Schema::table('player_profiles', function (Blueprint $table) {
            $table->string('dni')->nullable()->after('last_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left as no-op. DNI is a baseline field in player_profiles.
    }
};
