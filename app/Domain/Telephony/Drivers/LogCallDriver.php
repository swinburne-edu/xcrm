<?php

namespace App\Domain\Telephony\Drivers;

use App\Domain\Telephony\Contracts\CallDriver;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Development driver: no call is placed, the request is only written to the log. */
class LogCallDriver implements CallDriver
{
    public function dial(User $agent, string $toNumber, string $callId): ?string
    {
        Log::info('Click-to-call (log driver)', ['agent' => $agent->id, 'to' => $toNumber, 'call_id' => $callId]);

        return null;
    }
}
