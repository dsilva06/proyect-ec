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
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->decimal('entry_fee_usd', 10, 2)->default(0)->after('max_teams');
        });

        DB::table('tournament_categories')->update([
            'entry_fee_usd' => DB::raw('entry_fee_cents / 100'),
        ]);

        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->dropColumn('entry_fee_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->integer('entry_fee_cents')->default(0)->after('max_teams');
        });

        DB::table('tournament_categories')->update([
            'entry_fee_cents' => DB::raw('entry_fee_usd * 100'),
        ]);

        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->dropColumn('entry_fee_usd');
        });
    }
};
