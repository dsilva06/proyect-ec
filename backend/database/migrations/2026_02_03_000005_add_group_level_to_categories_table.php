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
        // Clear existing categories so we can enforce unique group/level pairs.
        DB::table('categories')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->string('group_code')->after('name');
            $table->string('level_code')->after('group_code');
            $table->string('display_name')->nullable()->after('level_code');

            $table->index(['group_code']);
            $table->index(['level_code']);
            $table->unique(['group_code', 'level_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['group_code', 'level_code']);
            $table->dropIndex(['group_code']);
            $table->dropIndex(['level_code']);
            $table->dropColumn(['group_code', 'level_code', 'display_name']);
        });
    }
};
