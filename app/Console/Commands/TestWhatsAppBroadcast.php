<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestWhatsAppBroadcast extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcast:test-whatsapp {phone?}';

    protected $description = 'Test broadcasting a WhatsApp message event';

    public function handle()
    {
        $phone = $this->argument('phone') ?? '966500000000';

        $this->info("Dispatching test message from {$phone}...");

        $payload = [
            'phone_number_id' => '517668101431248',
            'waba_id' => '987654321',
            'from' => $phone,
            'to' => '123456789',
            'type' => 'text',
            'body' => 'Test message from server at ' . now(),
            'status' => 'received',
            'message_id' => 'test_msg_' . uniqid(),
            'direction' => 'incoming',
            'raw_payload' => ['test' => true]
        ];

        \App\Events\WhatsAppMessageReceived::dispatch($payload);

        $this->info('Event dispatched! Check your frontend console/network tab.');
    }
}
