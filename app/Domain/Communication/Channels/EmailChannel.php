<?php

namespace App\Domain\Communication\Channels;

use App\Domain\Communication\Contracts\MessageChannel;
use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Data\SendResult;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Plain-text email through the application mailer. */
class EmailChannel implements MessageChannel
{
    public function send(OutboundMessage $message): SendResult
    {
        try {
            Mail::raw($message->body, function ($mail) use ($message): void {
                $mail->to($message->recipient)->subject($message->subject ?: 'Thông báo từ nhà trường');
            });
        } catch (Throwable $e) {
            return SendResult::failed($e->getMessage());
        }

        return SendResult::sent();
    }
}
