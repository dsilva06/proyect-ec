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
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('status_id')
                ->nullable()
                ->after('created_by')
                ->constrained('statuses')
                ->restrictOnDelete();

            $table->index('status_id');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('role')
                ->default('partner')
                ->after('slot');
        });

        DB::table('team_members')
            ->where('slot', 1)
            ->update(['role' => 'captain']);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
        });
    }
};
