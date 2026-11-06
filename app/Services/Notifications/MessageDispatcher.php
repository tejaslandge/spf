<?php

namespace App\Services\Notifications;

use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Jobs\SendMessage;
use App\Models\MessageLog;
use Illuminate\Database\Eloquent\Model;

class MessageDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(
        MessageChannel $channel,
        string $recipient,
        string $body,
        ?string $template = null,
        ?Model $related = null,
        ?int $userId = null,
    ): MessageLog {
        $log = MessageLog::query()->create([
            'channel' => $channel,
            'recipient' => $recipient,
            'template' => $template,
            'payload' => ['body' => $body],
            'status' => MessageStatus::Queued,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'user_id' => $userId ?? auth()->id(),
        ]);

        SendMessage::dispatch($log);

        return $log;
    }
}
