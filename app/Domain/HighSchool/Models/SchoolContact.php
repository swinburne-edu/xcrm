<?php

namespace App\Domain\HighSchool\Models;

use App\Domain\HighSchool\Enums\ContactRole;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasCustomData;
use Database\Factories\SchoolContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person at a high school (principal, career counselor, homeroom teacher…). One contact per school can be primary.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $high_school_id
 * @property string $full_name
 * @property ContactRole|null $role
 * @property string|null $homeroom_class
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $zalo
 * @property int|null $relationship_level
 * @property bool $is_primary
 * @property string|null $note
 */
#[Fillable(['organization_id', 'high_school_id', 'full_name', 'role', 'homeroom_class', 'phone', 'email', 'zalo', 'relationship_level', 'is_primary', 'note'])]
#[UseFactory(SchoolContactFactory::class)]
class SchoolContact extends Model
{
    /** @use HasFactory<SchoolContactFactory> */
    use BelongsToOrganization, HasAuditColumns, HasCustomData, HasFactory;

    protected $attributes = ['is_primary' => false];

    protected function casts(): array
    {
        return [
            'role' => ContactRole::class,
            'relationship_level' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<HighSchool, $this>
     */
    public function highSchool(): BelongsTo
    {
        return $this->belongsTo(HighSchool::class);
    }
}
