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
        Schema::table('open_entries', function (Blueprint $table) {
            $table->timestamp('paid_at')
                ->nullable()
                ->after('assignment_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->foreignId('registration_id')->nullable()->change();
            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();

            $table->foreignId('open_entry_id')
                ->nullable()
                ->after('registration_id')
                ->constrained('open_entries')
                ->nullOnDelete();

            $table->index(['open_entry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['open_entry_id']);
            $table->dropForeign(['open_entry_id']);
            $table->dropForeign(['registration_id']);
            $table->dropColumn('open_entry_id');
            $table->foreignId('registration_id')->nullable(false)->change();
            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
        });

        Schema::table('open_entries', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
