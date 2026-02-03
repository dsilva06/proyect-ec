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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_category_id')->constrained('tournament_categories')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->integer('queue_position')->nullable();
            $table->integer('seed_number')->nullable();
            $table->integer('team_ranking_score')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes_admin')->nullable();
            $table->timestamps();

            $table->unique(['tournament_category_id', 'team_id']);
            $table->index(['tournament_category_id', 'status_id']);
            $table->index(['tournament_category_id', 'seed_number']);
            $table->index(['tournament_category_id', 'queue_position']);
            $table->index(['payment_due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
