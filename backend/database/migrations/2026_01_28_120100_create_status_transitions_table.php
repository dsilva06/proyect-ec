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
        Schema::create('status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->foreignId('from_status_id')->constrained('statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses')->restrictOnDelete();
            $table->json('allowed_roles')->nullable();
            $table->timestamps();

            $table->unique(['module', 'from_status_id', 'to_status_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
    }
};
