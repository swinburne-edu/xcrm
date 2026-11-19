<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Workflow\Enums\WorkflowObject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property WorkflowObject $object
 * @property array{type: string, field?: string} $trigger
 * @property list<array{field: string, operator: string, value?: mixed}>|null $conditions
 * @property list<array<string, mixed>> $actions
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'name', 'object', 'trigger', 'conditions', 'actions', 'is_active'])]
class Workflow extends Model
{
    use BelongsToOrganization, HasAuditColumns, SoftDeletes;

    protected $attributes = ['is_active' => false];

    protected function casts(): array
    {
        return ['object' => WorkflowObject::class, 'trigger' => 'array', 'conditions' => 'array', 'actions' => 'array', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<WorkflowRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class)->orderByDesc('ran_at')->orderByDesc('id');
    }
}
