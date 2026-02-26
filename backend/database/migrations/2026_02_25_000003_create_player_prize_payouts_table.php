<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_prize_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('tournament_category_id')->constrained('tournament_categories')->cascadeOnDelete();
            $table->enum('position', ['champion', 'runner_up', 'semifinalist']);
            $table->integer('amount_eur_cents');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['tournament_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_prize_payouts');
    }
};
