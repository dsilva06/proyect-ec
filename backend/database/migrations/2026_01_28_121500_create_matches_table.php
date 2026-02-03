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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained('brackets')->cascadeOnDelete();
            $table->integer('round_number');
            $table->integer('match_number');
            $table->foreignId('registration_a_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('registration_b_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('court')->nullable();
            $table->json('score_json')->nullable();
            $table->foreignId('winner_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('updated_at_daily')->nullable();
            $table->timestamps();

            $table->index(['bracket_id', 'round_number']);
            $table->index(['status_id']);
            $table->index(['scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
