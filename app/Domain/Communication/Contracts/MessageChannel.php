<?php

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Data\SendResult;

/**
 * A way to reach people: one driver per channel (Zalo ZNS, SMS, email). A driver never throws for a provider
 * refusal or a missing configuration; it returns SendResult::failed() so the log row records why.
 */
interface MessageChannel
{
    public function send(OutboundMessage $message): SendResult;
}
