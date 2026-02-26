<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_ranking_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('win_points')->default(10);
            $table->integer('final_played_bonus')->default(5);
            $table->integer('final_won_bonus')->default(8);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_ranking_rules');
    }
};
