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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circuit_id')->nullable()->constrained('circuits')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('mode');
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province_state')->nullable();
            $table->string('country')->nullable();
            $table->string('timezone')->default('America/New_York');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('registration_open_at')->nullable();
            $table->timestamp('registration_close_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status_id']);
            $table->index(['start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
