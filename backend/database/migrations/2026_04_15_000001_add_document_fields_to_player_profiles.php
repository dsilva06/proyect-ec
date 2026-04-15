<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_profiles')) {
            return;
        }

        Schema::table('player_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('player_profiles', 'document_type')) {
                $table->string('document_type', 20)->nullable()->after('last_name');
            }

            if (! Schema::hasColumn('player_profiles', 'document_number')) {
                $table->string('document_number', 40)->nullable()->unique()->after('document_type');
            }
        });

        DB::table('player_profiles')
            ->whereNotNull('dni')
            ->orderBy('user_id')
            ->get(['user_id', 'dni'])
            ->each(function (object $profile): void {
                $document = strtoupper((string) $profile->dni);
                $document = preg_replace('/[^A-Z0-9]+/', '', $document) ?: null;

                if (! $document) {
                    return;
                }

                $documentType = match (true) {
                    preg_match('/^\d{8}[A-Z]$/', $document) === 1 => 'DNI',
                    preg_match('/^[XYZ]\d{7}[A-Z]$/', $document) === 1 => 'NIE',
                    default => 'PASSPORT',
                };

                DB::table('player_profiles')
                    ->where('user_id', $profile->user_id)
                    ->update([
                        'document_type' => $documentType,
                        'document_number' => Str::limit($document, 40, ''),
                        'dni' => Str::limit($document, 40, ''),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('player_profiles')) {
            return;
        }

        Schema::table('player_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('player_profiles', 'document_number')) {
                $table->dropUnique(['document_number']);
                $table->dropColumn('document_number');
            }

            if (Schema::hasColumn('player_profiles', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
};
