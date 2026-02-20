<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('is_wildcard')->default(false)->after('team_ranking_score');
            $table->boolean('wildcard_fee_waived')->default(false)->after('is_wildcard');
            $table->foreignId('wildcard_invitation_id')
                ->nullable()
                ->constrained('invitations')
                ->nullOnDelete()
                ->after('wildcard_fee_waived');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wildcard_invitation_id');
            $table->dropColumn(['is_wildcard', 'wildcard_fee_waived']);
        });
    }
};
