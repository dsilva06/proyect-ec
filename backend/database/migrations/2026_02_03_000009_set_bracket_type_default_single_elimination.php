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

            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE brackets MODIFY type VARCHAR(255) NOT NULL DEFAULT 'single_elimination'");
            } elseif ($driver === 'pgsql') {
                DB::statement("ALTER TABLE brackets ALTER COLUMN type SET DEFAULT 'single_elimination'");
                DB::statement('ALTER TABLE brackets ALTER COLUMN type SET NOT NULL');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('brackets')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE brackets MODIFY type VARCHAR(255) NOT NULL");
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE brackets ALTER COLUMN type DROP DEFAULT');
                DB::statement('ALTER TABLE brackets ALTER COLUMN type SET NOT NULL');
            }
        }
    }
};
