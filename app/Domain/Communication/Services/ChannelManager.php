<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Channels\EmailChannel;
use App\Domain\Communication\Channels\LogChannel;
use App\Domain\Communication\Channels\SmsChannel;
use App\Domain\Communication\Channels\ZnsChannel;
use App\Domain\Communication\Contracts\MessageChannel;
use App\Domain\Communication\Enums\MessageChannelType;

/** Picks the driver of a channel from config/communication.php ("log" or the real provider). */
class ChannelManager
{
    public function for(MessageChannelType $type): MessageChannel
    {
        if (config("communication.drivers.{$type->value}") === 'log') {
            return app(LogChannel::class);
        }

        return match ($type) {
            MessageChannelType::Zns => app(ZnsChannel::class),
            MessageChannelType::Sms => app(SmsChannel::class),
            MessageChannelType::Email => app(EmailChannel::class),
        };
    }
}
