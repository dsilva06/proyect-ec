<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statuses')) {
            return;
        }

        $statuses = [
            [
                'module' => 'team',
                'code' => 'confirmed',
                'label' => 'Confirmed',
                'is_terminal' => false,
                'sort_order' => 1,
            ],
            [
                'module' => 'team',
                'code' => 'cancelled',
                'label' => 'Cancelled',
                'is_terminal' => true,
                'sort_order' => 2,
            ],
            [
                'module' => 'team',
                'code' => 'expired',
                'label' => 'Expired',
                'is_terminal' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($statuses as $status) {
            $existing = DB::table('statuses')
                ->where('module', $status['module'])
                ->where('code', $status['code'])
                ->exists();

            if ($existing) {
                DB::table('statuses')
                    ->where('module', $status['module'])
                    ->where('code', $status['code'])
                    ->update([
                        'label' => $status['label'],
                        'is_terminal' => $status['is_terminal'],
                        'sort_order' => $status['sort_order'],
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('statuses')->insert([
                ...$status,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep seeded statuses because teams may reference them in production.
    }
};
