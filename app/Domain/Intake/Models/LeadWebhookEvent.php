<?php

namespace App\Domain\Intake\Models;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Intake\Enums\LeadEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lead received on an integration webhook. external_id (Facebook leadgen_id / TikTok lead id) makes it idempotent.
 *
 * @property int $id
 * @property int $integration_id
 * @property string|null $external_id
 * @property LeadEventStatus $status
 * @property string|null $error
 * @property array<string, mixed>|null $payload
 * @property int|null $applicant_id
 */
#[Fillable(['integration_id', 'external_id', 'status', 'error', 'payload', 'applicant_id'])]
class LeadWebhookEvent extends Model
{
    protected function casts(): array
    {
        return ['status' => LeadEventStatus::class, 'payload' => 'array'];
    }

    /** @return BelongsTo<LeadIntegration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(LeadIntegration::class, 'integration_id');
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
