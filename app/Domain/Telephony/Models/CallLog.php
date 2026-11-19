<?php

namespace App\Domain\Telephony\Models;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Telephony\Enums\CallStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One call, inbound (in) or outbound (out). external_id is the call id of the provider; for click-to-call it is
 * generated here and the provider is expected to echo it back in its events.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $integration_id
 * @property string $external_id
 * @property string $direction
 * @property string|null $from_number
 * @property string|null $to_number
 * @property string|null $phone_normalized
 * @property int|null $applicant_id
 * @property int|null $user_id
 * @property CallStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $answered_at
 * @property int|null $duration_sec
 * @property string|null $recording_url
 * @property string|null $error
 * @property int|null $activity_id
 */
#[Fillable(['organization_id', 'integration_id', 'external_id', 'direction', 'from_number', 'to_number', 'phone_normalized', 'applicant_id', 'user_id', 'status', 'started_at', 'answered_at', 'duration_sec', 'recording_url', 'error', 'activity_id'])]
class CallLog extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['status' => CallStatus::class, 'started_at' => 'datetime', 'answered_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Applicant, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The number of the person outside: the caller of an inbound call, the callee of an outbound one. */
    public function customerNumber(): ?string
    {
        return $this->direction === 'in' ? $this->from_number : $this->to_number;
    }
}
