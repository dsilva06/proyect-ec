<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('purpose')->default('general')->after('tournament_category_id');
            $table->string('partner_email')->nullable()->after('email');
            $table->string('partner_name')->nullable()->after('partner_email');
            $table->boolean('wildcard_fee_waived')->default(false)->after('partner_name');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'partner_email', 'partner_name', 'wildcard_fee_waived']);
        });
    }
};
