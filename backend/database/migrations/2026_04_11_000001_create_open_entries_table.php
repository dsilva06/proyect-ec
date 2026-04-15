<?php

use App\Models\OpenEntry;
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
        if (Schema::hasTable('open_entries')) {
            return;
        }

        Schema::create('open_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('segment', 20);
            $table->string('partner_email');
            $table->string('partner_first_name');
            $table->string('partner_last_name');
            $table->string('partner_dni', 50);
            $table->string('assignment_status', 50)->default(OpenEntry::ASSIGNMENT_PENDING);
            $table->foreignId('assigned_tournament_category_id')->nullable()->constrained('tournament_categories')->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->unique()->constrained('registrations')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes_admin')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'team_id']);
            $table->index(['tournament_id', 'assignment_status']);
            $table->index(['assigned_tournament_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_entries');
    }
};
