<?php

namespace App\Domain\Communication\Models;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Communication\Enums\MessageStatus;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message to one recipient: the text as it was rendered, its status and the provider's answer.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $template_id
 * @property string|null $batch_id
 * @property MessageChannelType $channel
 * @property string|null $recipient
 * @property int|null $applicant_id
 * @property int|null $user_id
 * @property string|null $subject
 * @property string|null $body
 * @property MessageStatus $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
#[Fillable(['organization_id', 'template_id', 'batch_id', 'channel', 'recipient', 'applicant_id', 'user_id', 'subject', 'body', 'status', 'provider_message_id', 'error', 'sent_at'])]
class MessageLog extends Model
{
    use BelongsToOrganization;

    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return ['channel' => MessageChannelType::class, 'status' => MessageStatus::class, 'sent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class)->withTrashed();
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
}
