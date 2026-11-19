<?php

namespace App\Domain\Communication\Models;

use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable message. The body may contain variables such as {{applicant.full_name}} (TemplateRenderer::VARIABLES).
 * For Zalo ZNS, provider_template_id is the id of the template Zalo approved and the variables become its template_data.
 *
 * @property int $id
 * @property int $organization_id
 * @property MessageChannelType $channel
 * @property string $name
 * @property string|null $subject
 * @property string $body
 * @property string|null $provider_template_id
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'channel', 'name', 'subject', 'body', 'provider_template_id', 'is_active'])]
class MessageTemplate extends Model
{
    use BelongsToOrganization, HasAuditColumns, SoftDeletes;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['channel' => MessageChannelType::class, 'is_active' => 'boolean'];
    }
}
