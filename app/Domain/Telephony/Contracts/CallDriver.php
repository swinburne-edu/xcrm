<?php

namespace App\Domain\Telephony\Contracts;

use App\Models\User;

/** Asks the telephone system to start a call. Returns null when accepted, else the reason it was not. */
interface CallDriver
{
    public function dial(User $agent, string $toNumber, string $callId): ?string;
}
