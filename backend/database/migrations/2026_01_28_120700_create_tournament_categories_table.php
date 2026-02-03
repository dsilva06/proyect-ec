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
        Schema::create('tournament_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->integer('max_teams')->default(32);
            $table->integer('entry_fee_cents');
            $table->string('currency')->default('USD');
            $table->string('acceptance_type')->default('waitlist');
            $table->integer('acceptance_window_hours')->nullable();
            $table->string('seeding_rule')->default('ranking_desc');
            $table->foreignId('status_id')->nullable()->constrained('statuses')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tournament_id', 'category_id']);
            $table->index(['tournament_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_categories');
    }
};
