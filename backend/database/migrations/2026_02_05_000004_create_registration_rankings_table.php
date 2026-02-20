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
        Schema::create('registration_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('tournament_category_id')->constrained('tournament_categories')->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invited_email')->nullable();
            $table->integer('ranking_value');
            $table->string('ranking_source')->nullable();
            $table->timestamps();

            $table->unique(['registration_id', 'slot'], 'reg_rank_reg_slot_uq');
            $table->unique(['tournament_category_id', 'ranking_value'], 'reg_rank_tcat_rank_uq');
            $table->index(['tournament_category_id'], 'reg_rank_tcat_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_rankings');
    }
};
