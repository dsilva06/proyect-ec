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
        Schema::table('team_invites', function (Blueprint $table) {
            $table->timestamp('email_sent_at')
                ->nullable()
                ->after('expires_at');
            $table->text('email_last_error')
                ->nullable()
                ->after('email_sent_at');
            $table->unsignedInteger('email_attempts')
                ->default(0)
                ->after('email_last_error');

            $table->index(['status_id', 'invited_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_invites', function (Blueprint $table) {
            $table->dropIndex(['status_id', 'invited_email']);
            $table->dropColumn([
                'email_sent_at',
                'email_last_error',
                'email_attempts',
            ]);
        });
    }
};

