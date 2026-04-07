<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StatusSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $definitions = [
            'registration' => [
                ['code' => 'pending', 'terminal' => false],
                ['code' => 'waitlisted', 'terminal' => false],
                ['code' => 'accepted', 'terminal' => false],
                ['code' => 'payment_pending', 'terminal' => false],
                ['code' => 'awaiting_partner_acceptance', 'terminal' => false],
                ['code' => 'paid', 'terminal' => true],
                ['code' => 'expired', 'terminal' => true],
                ['code' => 'rejected', 'terminal' => true],
                ['code' => 'cancelled', 'terminal' => true],
            ],
            'payment' => [
                ['code' => 'created', 'terminal' => false],
                ['code' => 'pending', 'terminal' => false],
                ['code' => 'requires_action', 'terminal' => false],
                ['code' => 'processing', 'terminal' => false],
                ['code' => 'succeeded', 'terminal' => true],
                ['code' => 'failed', 'terminal' => true],
                ['code' => 'refunded', 'terminal' => true],
                ['code' => 'cancelled', 'terminal' => true],
            ],
            'tournament' => [
                ['code' => 'draft', 'terminal' => false],
                ['code' => 'published', 'terminal' => false],
                ['code' => 'registration_open', 'terminal' => false],
                ['code' => 'registration_closed', 'terminal' => false],
                ['code' => 'in_progress', 'terminal' => false],
                ['code' => 'completed', 'terminal' => true],
                ['code' => 'cancelled', 'terminal' => true],
            ],
            'match' => [
                ['code' => 'scheduled', 'terminal' => false],
                ['code' => 'in_progress', 'terminal' => false],
                ['code' => 'completed', 'terminal' => true],
                ['code' => 'walkover', 'terminal' => true],
                ['code' => 'cancelled', 'terminal' => true],
            ],
            'bracket' => [
                ['code' => 'draft', 'terminal' => false],
                ['code' => 'published', 'terminal' => false],
                ['code' => 'locked', 'terminal' => true],
            ],
            'lead' => [
                ['code' => 'new', 'terminal' => false],
                ['code' => 'contacted', 'terminal' => false],
                ['code' => 'qualified', 'terminal' => false],
                ['code' => 'closed', 'terminal' => true],
            ],
            'refund' => [
                ['code' => 'requested', 'terminal' => false],
                ['code' => 'processing', 'terminal' => false],
                ['code' => 'succeeded', 'terminal' => true],
                ['code' => 'failed', 'terminal' => true],
                ['code' => 'cancelled', 'terminal' => true],
            ],
            'team_invite' => [
                ['code' => 'pending', 'terminal' => false],
                ['code' => 'accepted', 'terminal' => true],
                ['code' => 'rejected', 'terminal' => true],
                ['code' => 'expired', 'terminal' => true],
                ['code' => 'revoked', 'terminal' => true],
            ],
            'team' => [
                ['code' => 'pending_partner_acceptance', 'terminal' => false],
                ['code' => 'confirmed', 'terminal' => false],
                ['code' => 'cancelled', 'terminal' => true],
                ['code' => 'expired', 'terminal' => true],
            ],
            'invitation' => [
                ['code' => 'pending', 'terminal' => false],
                ['code' => 'accepted', 'terminal' => true],
                ['code' => 'expired', 'terminal' => true],
                ['code' => 'cancelled', 'terminal' => true],
            ],
        ];

        foreach ($definitions as $module => $statuses) {
            foreach ($statuses as $index => $status) {
                Status::updateOrCreate(
                    ['module' => $module, 'code' => $status['code']],
                    [
                        'label' => Str::title(str_replace('_', ' ', $status['code'])),
                        'is_terminal' => $status['terminal'],
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
