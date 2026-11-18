<?php

namespace App\Http\Controllers\Activity;

use App\Domain\Activity\Actions\DeleteTask;
use App\Domain\Activity\Actions\SaveTask;
use App\Domain\Activity\Actions\SetTaskStatus;
use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Activity\Enums\TaskType;
use App\Domain\Activity\Models\Task;
use App\Domain\Activity\Services\TimelineSubject;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\TaskRequest;
use App\Http\Requests\Activity\TaskStatusRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /tasks: a user's to-dos in the tabs Hôm nay / Quá hạn / Sắp tới / Đã xong (tasks are visible by data scope:
 * a user sees theirs, a manager the team's / all). "Quá hạn" = open tasks due before today; "Hôm nay" = due today
 * (flagged late once the hour has passed); "Sắp tới" = later or without a due date.
 */
class TaskController extends Controller
{
    public const VIEWS = ['today', 'overdue', 'upcoming', 'done'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'today';

        $query = Task::query()->with(['assignee:id,name', 'subject' => fn (MorphTo $m) => $m->morphWith([AdmissionCase::class => ['applicant:id,uuid,full_name']])]);
        $this->bucket($query, $view);

        $list = ListQuery::for($query, $request)
            ->filters(
                Filter::exact('type'),
                Filter::exact('priority'),
                Filter::exact('assignee_id'),
                Filter::search('q', ['title']),
            )
            ->sorts(['due_at', 'priority', 'title', 'created_at'])
            ->defaultSort($view === 'done' ? '-completed_at' : 'due_at');

        return Inertia::render('Tasks/Index', [
            'tasks' => $list->toProps(fn (Task $t) => self::row($t)),
            'view' => $view,
            'counts' => collect(self::VIEWS)->mapWithKeys(fn (string $v) => [$v => $this->bucket(Task::query(), $v)->count()])->all(),
            'options' => [
                'types' => collect(TaskType::cases())->map(fn (TaskType $t) => ['value' => $t->value, 'label' => $t->label()])->all(),
                'priorities' => collect(TaskPriority::cases())->map(fn (TaskPriority $t) => ['value' => $t->value, 'label' => $t->label()])->all(),
            ],
            'can' => ['create' => $request->user()->can('create', Task::class)],
        ]);
    }

    public function store(TaskRequest $request, SaveTask $save, TimelineSubject $subjects): RedirectResponse|JsonResponse
    {
        $subject = null;

        if ($request->filled('subject_type')) {
            try {
                $subject = $subjects->resolve($request->string('subject_type')->toString(), $request->string('subject')->toString());
            } catch (ModelNotFoundException) {
                abort(404);
            }

            $this->authorize('update', $subject);
        }

        $task = $save->handle(null, $request->validated(), $request->user(), $subject);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['task' => self::row($task)], 201)
            : $this->toast('Đã tạo việc.');
    }

    public function update(TaskRequest $request, int $id, SaveTask $save): RedirectResponse
    {
        $save->handle(Task::query()->findOrFail($id), $request->validated(), $request->user());

        return $this->toast('Đã cập nhật việc.');
    }

    public function status(TaskStatusRequest $request, int $id, SetTaskStatus $set): RedirectResponse|JsonResponse
    {
        $task = $set->handle(Task::query()->findOrFail($id), TaskStatus::from($request->input('status')), $request->user());

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['task' => self::row($task)])
            : $this->toast($task->status === TaskStatus::Done ? 'Đã hoàn thành việc.' : 'Đã cập nhật việc.');
    }

    public function destroy(int $id, DeleteTask $delete): RedirectResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('delete', $task);
        $delete->handle($task);

        return $this->toast('Đã xoá việc.');
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function bucket(Builder $query, string $view): Builder
    {
        $tz = config('app.display_timezone');
        $startOfToday = CarbonImmutable::now($tz)->startOfDay()->utc();
        $endOfToday = CarbonImmutable::now($tz)->endOfDay()->utc();

        if ($view === 'done') {
            return $query->where('status', '!=', 'todo');
        }

        $query->where('status', 'todo');

        return match ($view) {
            'overdue' => $query->where('due_at', '<', $startOfToday),
            'today' => $query->whereBetween('due_at', [$startOfToday, $endOfToday]),
            default => $query->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '>', $endOfToday)),
        };
    }

    /** @return array<string, mixed> */
    public static function row(Task $t): array
    {
        $subject = $t->relationLoaded('subject') ? $t->subject : $t->subject()->first();
        [$label, $href] = match (true) {
            $subject instanceof AdmissionCase => [$subject->applicant?->full_name ?? 'Hồ sơ #'.$subject->id, $subject->applicant ? "/applicants/{$subject->applicant->uuid}" : null],
            $subject instanceof Applicant => [$subject->full_name, "/applicants/{$subject->uuid}"],
            $subject instanceof HighSchool => [$subject->name, "/high-schools/{$subject->id}"],
            default => [null, null],
        };

        return [
            'id' => $t->id,
            'title' => $t->title,
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'priority' => $t->priority->value,
            'priority_label' => $t->priority->label(),
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'due_at' => $t->due_at?->toIso8601String(),
            'due_input' => DisplayTime::toInput($t->due_at),
            'overdue' => $t->isOverdue(),
            'completed_at' => $t->completed_at?->toIso8601String(),
            'assignee_id' => $t->assignee_id,
            'assignee_name' => $t->assignee?->name,
            'subject_label' => $label,
            'subject_href' => $href,
        ];
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/tasks');
    }
}
