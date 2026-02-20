<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('not_before_at')->nullable()->after('scheduled_at');
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable()->after('not_before_at');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['not_before_at', 'estimated_duration_minutes']);
        });
    }
};
