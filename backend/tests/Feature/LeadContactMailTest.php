<?php

namespace Tests\Feature;

use App\Mail\LeadReceivedMail;
use App\Models\Status;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeadContactMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_lead_queues_notification_email(): void
    {
        $this->seed(StatusSeeder::class);
        Mail::fake();
        config()->set('mail.leads_inbox', 'ventas@example.com');

        $payload = [
            'type' => 'contact',
            'full_name' => 'Diego Silva',
            'email' => 'diego@example.com',
            'phone' => '+34 600000000',
            'company' => 'Estars',
            'message' => 'Necesito informacion del circuito.',
            'source' => 'homepage',
        ];

        $this->postJson('/api/public/leads', $payload)->assertCreated();

        Mail::assertQueued(LeadReceivedMail::class);
    }

    public function test_statuses_endpoint_is_read_only(): void
    {
        $before = Status::query()->count();

        $this->getJson('/api/statuses')->assertOk();

        $after = Status::query()->count();
        $this->assertSame($before, $after);
    }
}
