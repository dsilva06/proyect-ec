<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->time('day_start_time')->nullable()->after('registration_close_at');
            $table->time('day_end_time')->nullable()->after('day_start_time');
            $table->integer('match_duration_minutes')->nullable()->after('day_end_time');
            $table->unsignedSmallInteger('courts_count')->nullable()->after('match_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['day_start_time', 'day_end_time', 'match_duration_minutes', 'courts_count']);
        });
    }
};
