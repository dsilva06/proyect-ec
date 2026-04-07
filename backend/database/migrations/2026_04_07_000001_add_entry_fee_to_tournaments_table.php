<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedInteger('entry_fee_amount')->default(0)->after('end_date');
            $table->string('entry_fee_currency', 10)->default('USD')->after('entry_fee_amount');
        });

        $categoryColumns = Schema::getColumnListing('tournament_categories');
        $amountColumn = in_array('entry_fee_amount', $categoryColumns, true)
            ? 'entry_fee_amount'
            : (in_array('entry_fee_usd', $categoryColumns, true) ? 'entry_fee_usd' : null);

        if (! $amountColumn) {
            return;
        }

        $tournamentIds = DB::table('tournaments')->pluck('id');

        foreach ($tournamentIds as $tournamentId) {
            $firstCategory = DB::table('tournament_categories')
                ->where('tournament_id', $tournamentId)
                ->select($amountColumn, 'currency')
                ->orderBy('id')
                ->first();

            if (! $firstCategory) {
                continue;
            }

            DB::table('tournaments')
                ->where('id', $tournamentId)
                ->update([
                    'entry_fee_amount' => max(0, (int) ($firstCategory->{$amountColumn} ?? 0)),
                    'entry_fee_currency' => (string) ($firstCategory->currency ?: 'USD'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['entry_fee_amount', 'entry_fee_currency']);
        });
    }
};
