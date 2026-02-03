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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_intent_id')->unique();
            $table->integer('amount_cents');
            $table->string('currency');
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('failure_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['registration_id']);
            $table->index(['status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
