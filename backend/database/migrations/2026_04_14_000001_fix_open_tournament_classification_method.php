<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tournaments')
            ->where('mode', 'open')
            ->where('classification_method', '!=', 'referee_assigned')
            ->update(['classification_method' => 'referee_assigned']);
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring self_selected on OPEN tournaments
        // would re-break them. No rollback.
    }
};
