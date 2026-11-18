<?php

namespace App\Domain\Intake\Models;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A web form embedded on the university site. Anonymous visitors post it to POST /api/public/leads with the
 * form's uuid; the form decides the lead source / campaign and which fields are shown (and required).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $uuid
 * @property string $name
 * @property int|null $campaign_id
 * @property int|null $source_id
 * @property list<array{key: string, required: bool}> $fields
 * @property string|null $success_message
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'name', 'campaign_id', 'source_id', 'fields', 'success_message', 'is_active'])]
class Form extends Model
{
    use BelongsToOrganization, HasAuditColumns, SoftDeletes;

    /** key => label of the fields a form may show; full_name and phone are always present. */
    public const AVAILABLE_FIELDS = [
        'full_name' => 'Họ và tên',
        'phone' => 'Số điện thoại',
        'email' => 'Email',
        'province' => 'Tỉnh/thành phố',
        'high_school' => 'Trường THPT',
        'graduation_year' => 'Năm tốt nghiệp',
        'major' => 'Ngành quan tâm',
        'note' => 'Lời nhắn',
    ];

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        static::creating(function (self $form): void {
            $form->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['fields' => 'array', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }
}
