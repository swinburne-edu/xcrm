<?php

namespace App\Domain\Activity\Models;

use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Activity\Enums\TaskType;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Models\User;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A to-do of a user, optionally about a record (a case or an applicant). Data scope: a user sees the
 * tasks assigned to them; users with scope "all" see every task of the organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $title
 * @property TaskType $type
 * @property TaskPriority $priority
 * @property int|null $assignee_id
 * @property Carbon|null $due_at
 * @property TaskStatus $status
 * @property Carbon|null $completed_at
 */
#[Fillable(['organization_id', 'subject_type', 'subject_id', 'title', 'type', 'priority', 'assignee_id', 'due_at', 'status', 'completed_at'])]
#[UseFactory(TaskFactory::class)]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToOrganization, HasAuditColumns, HasDataScope, HasFactory;

    protected $attributes = ['type' => 'other', 'priority' => 'normal', 'status' => 'todo'];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function dataScopeOwnerColumn(): string
    {
        return 'assignee_id';
    }

    public function dataScopeTeamColumn(): ?string
    {
        return null;
    }

    public function isOverdue(): bool
    {
        return $this->status === TaskStatus::Todo && $this->due_at !== null && $this->due_at->isPast();
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
