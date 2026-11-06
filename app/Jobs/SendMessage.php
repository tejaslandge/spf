<?php

namespace App\Jobs;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Services\Notifications\SmsGateway;
use App\Services\Notifications\WhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public MessageLog $messageLog) {}

    public function handle(WhatsAppGateway $whatsapp, SmsGateway $sms): void
    {
        $this->messageLog->refresh();

        try {
            $result = match ($this->messageLog->channel) {
                MessageChannel::Whatsapp => $whatsapp->send(
                    $this->messageLog->recipient,
                    $this->messageLog->payload ?? [],
                ),
                MessageChannel::Sms => $sms->send(
                    $this->messageLog->recipient,
                    (string) ($this->messageLog->payload['body'] ?? ''),
                ),
            };

            $this->messageLog->update([
                'status' => MessageStatus::Sent,
                'provider' => $this->messageLog->channel->value,
                'provider_message_id' => $result['provider_id'],
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            $this->messageLog->update([
                'status' => MessageStatus::Failed,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
