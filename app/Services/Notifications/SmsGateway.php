<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsGateway
{
    /**
     * @return array{provider_id: ?string}
     */
    public function send(string $recipient, string $body): array
    {
        $config = config('finance.notifications.sms');

        if (! $config['enabled'] || blank($config['sid']) || blank($config['token']) || blank($config['from'])) {
            throw new RuntimeException('Twilio SMS is not configured.');
        }

        $response = Http::withBasicAuth($config['sid'], $config['token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['sid']}/Messages.json", [
                'To' => $recipient,
                'From' => $config['from'],
                'Body' => $body,
            ])
            ->throw();

        return ['provider_id' => $response->json('sid')];
    }
}
