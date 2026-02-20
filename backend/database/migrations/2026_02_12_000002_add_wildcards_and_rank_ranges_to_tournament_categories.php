<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('wildcard_slots')->default(0)->after('max_teams');
            $table->integer('min_fip_rank')->nullable()->after('wildcard_slots');
            $table->integer('max_fip_rank')->nullable()->after('min_fip_rank');
            $table->integer('min_fep_rank')->nullable()->after('max_fip_rank');
            $table->integer('max_fep_rank')->nullable()->after('min_fep_rank');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_categories', function (Blueprint $table) {
            $table->dropColumn(['wildcard_slots', 'min_fip_rank', 'max_fip_rank', 'min_fep_rank', 'max_fep_rank']);
        });
    }
};
