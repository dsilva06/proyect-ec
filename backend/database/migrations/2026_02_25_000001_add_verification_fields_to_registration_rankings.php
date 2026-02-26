<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_rankings', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('ranking_source');
            $table->timestamp('verified_at')->nullable()->after('is_verified');
            $table->foreignId('verified_by_user_id')
                ->nullable()
                ->after('verified_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_rankings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['is_verified', 'verified_at']);
        });
    }
};
