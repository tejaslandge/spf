<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{provider_id: ?string}
     */
    public function send(string $recipient, array $payload): array
    {
        $config = config('finance.notifications.whatsapp');

        if (! $config['enabled'] || blank($config['phone_number_id']) || blank($config['access_token'])) {
            throw new RuntimeException('WhatsApp Cloud API is not configured.');
        }

        $body = isset($payload['template'])
            ? [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'template',
                'template' => $payload['template'],
            ]
            : [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => ['body' => $payload['body'] ?? ''],
            ];

        $response = Http::withToken($config['access_token'])
            ->acceptJson()
            ->post("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/messages", $body)
            ->throw();

        return ['provider_id' => $response->json('messages.0.id')];
    }
}
