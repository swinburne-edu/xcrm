<?php

namespace App\Domain\Activity\Models;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Catalog\Models\CallResult;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Models\User;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One entry of a record's timeline (applicant, admission case, high school, school contact, campaign).
 * Not the audit log (spatie activity_log): these are the things people see and write.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $subject_type morph alias
 * @property int $subject_id
 * @property ActivityType $type
 * @property string|null $direction in | out
 * @property string|null $title
 * @property string|null $body
 * @property int|null $call_result_id
 * @property int|null $duration_sec
 * @property array<string, mixed>|null $meta
 * @property Carbon $occurred_at
 * @property int|null $user_id
 */
#[Fillable(['organization_id', 'subject_type', 'subject_id', 'type', 'direction', 'title', 'body', 'call_result_id', 'duration_sec', 'recording_url', 'meta', 'occurred_at', 'user_id'])]
#[UseFactory(ActivityFactory::class)]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use BelongsToOrganization, HasFactory;

    /** Morph aliases that may carry a timeline. */
    public const SUBJECTS = ['applicant', 'admission_case', 'high_school', 'school_contact', 'campaign'];

    protected function casts(): array
    {
        return ['type' => ActivityType::class, 'meta' => 'array', 'occurred_at' => 'datetime', 'duration_sec' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CallResult, $this> */
    public function callResult(): BelongsTo
    {
        return $this->belongsTo(CallResult::class);
    }
}
