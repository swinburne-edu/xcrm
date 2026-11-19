<?php

namespace App\Domain\Telephony\Actions;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Telephony\Contracts\CallDriver;
use App\Domain\Telephony\Drivers\HttpCallDriver;
use App\Domain\Telephony\Drivers\LogCallDriver;
use App\Domain\Telephony\Enums\CallStatus;
use App\Domain\Telephony\Models\CallLog;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Click-to-call: logs an outbound call for the agent and asks the telephone system (driver in config/telephony.php)
 * to ring the agent and the applicant. The number comes from the applicant record on the server, so people who only
 * see a masked phone can still call.
 */
class DialCall
{
    /** @throws ValidationException */
    public function handle(User $agent, Applicant $applicant): CallLog
    {
        if (blank($applicant->phone_normalized)) {
            throw ValidationException::withMessages(['applicant' => 'Thí sinh chưa có số điện thoại.']);
        }

        $callId = (string) Str::uuid();
        $error = $this->driver()->dial($agent, $applicant->phone_normalized, $callId);

        return CallLog::query()->create([
            'organization_id' => $agent->organization_id,
            'external_id' => $callId,
            'direction' => 'out',
            'to_number' => $applicant->phone_normalized,
            'phone_normalized' => $applicant->phone_normalized,
            'applicant_id' => $applicant->id,
            'user_id' => $agent->id,
            'status' => $error === null ? CallStatus::Dialing : CallStatus::Failed,
            'started_at' => now(),
            'error' => $error,
        ]);
    }

    private function driver(): CallDriver
    {
        return config('telephony.driver') === 'http' ? app(HttpCallDriver::class) : app(LogCallDriver::class);
    }
}
