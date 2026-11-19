<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Workflow\Enums\WorkflowRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One execution of a workflow on one record. `log` lists what each action did (or why it was skipped / failed).
 *
 * @property int $id
 * @property int $workflow_id
 * @property string $subject_type
 * @property int $subject_id
 * @property WorkflowRunStatus $status
 * @property list<array{action: string, ok: bool, message: string}>|null $log
 * @property Carbon $ran_at
 */
#[Fillable(['organization_id', 'workflow_id', 'subject_type', 'subject_id', 'status', 'log', 'ran_at'])]
class WorkflowRun extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['status' => WorkflowRunStatus::class, 'log' => 'array', 'ran_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class)->withTrashed();
    }
}
