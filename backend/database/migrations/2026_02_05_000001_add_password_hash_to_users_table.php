<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_hash')->nullable()->after('password');
        });

        DB::table('users')->update([
            'password_hash' => DB::raw('password'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password_hash VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password_hash SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email_verified_at');
        });

        DB::table('users')->update([
            'password' => DB::raw('password_hash'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_hash');
        });
    }
};
