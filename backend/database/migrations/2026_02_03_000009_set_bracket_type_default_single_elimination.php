<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('brackets')) {
            DB::table('brackets')->update(['type' => 'single_elimination']);
            DB::statement("ALTER TABLE brackets MODIFY type VARCHAR(255) NOT NULL DEFAULT 'single_elimination'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('brackets')) {
            DB::statement("ALTER TABLE brackets MODIFY type VARCHAR(255) NOT NULL");
        }
    }
};
