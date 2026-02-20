<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ranking_source')->nullable()->after('last_login_at');
            $table->integer('ranking_value')->nullable()->after('ranking_source');
            $table->timestamp('ranking_verified_at')->nullable()->after('ranking_value');
            $table->foreignId('ranking_verified_by')
                ->nullable()
                ->after('ranking_verified_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('ranking_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ranking_verified_by']);
            $table->dropIndex(['ranking_value']);
            $table->dropColumn([
                'ranking_source',
                'ranking_value',
                'ranking_verified_at',
                'ranking_verified_by',
            ]);
        });
    }
};
