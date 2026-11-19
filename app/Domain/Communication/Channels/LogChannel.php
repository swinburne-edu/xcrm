<?php

namespace App\Domain\Communication\Channels;

use App\Domain\Communication\Contracts\MessageChannel;
use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Data\SendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development driver: nothing leaves the server, the message is written to the log. */
class LogChannel implements MessageChannel
{
    public function send(OutboundMessage $message): SendResult
    {
        Log::info('Message (log driver)', ['to' => $message->recipient, 'subject' => $message->subject, 'body' => $message->body]);

        return SendResult::sent('log-'.Str::random(10));
    }
}
