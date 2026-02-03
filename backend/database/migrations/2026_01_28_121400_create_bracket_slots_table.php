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
        Schema::create('bracket_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained('brackets')->cascadeOnDelete();
            $table->integer('slot_number');
            $table->foreignId('registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->integer('seed_number')->nullable();
            $table->timestamps();

            $table->unique(['bracket_id', 'slot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bracket_slots');
    }
};
