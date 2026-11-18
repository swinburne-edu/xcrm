<?php

namespace App\Http\Controllers\Activity;

use App\Domain\Activity\Actions\LogActivity;
use App\Domain\Activity\Models\Activity;
use App\Domain\Activity\Models\Task;
use App\Domain\Activity\Services\TimelineSubject;
use App\Domain\Catalog\Models\CallResult;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\TimelineEntryRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Timeline JSON for the <Timeline> component: GET /timeline?subject_type=&subject= (entries newest first,
 * "before" = last id seen for the next page) and POST /timeline (note, call, message, meeting).
 * The record is found through its data scope; reading needs "view", writing needs "update" on it.
 */
class TimelineController extends Controller
{
    private const PAGE = 20;

    public function __construct(private readonly TimelineSubject $subjects) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:64'],
            'before' => ['nullable', 'integer'],
            'options' => ['nullable', 'boolean'],
        ]);

        $subject = $this->subject($data['subject_type'], $data['subject']);
        $this->authorize('view', $subject);

        $query = $this->subjects->entries($subject)->with(['user:id,name', 'callResult:id,name']);

        if (! empty($data['before'])) {
            $query->where('activities.id', '<', (int) $data['before']);
        }

        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->limit(self::PAGE + 1)->get();

        $tasks = Task::query()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->where('status', 'todo')->with('assignee:id,name')->orderBy('due_at')->limit(20)->get();

        return response()->json([
            'entries' => $rows->take(self::PAGE)->map(fn (Activity $a) => $this->entry($a))->values()->all(),
            'has_more' => $rows->count() > self::PAGE,
            'tasks' => $tasks->map(fn (Task $t) => TaskController::row($t))->values()->all(),
            'call_results' => $request->boolean('options') ? CallResult::query()->orderBy('sort_order')->get(['id', 'name'])->all() : [],
            'can' => ['write' => $request->user()->can('update', $subject)],
        ]);
    }

    public function store(TimelineEntryRequest $request, LogActivity $log, CurrentCycle $cycle): JsonResponse
    {
        $subject = $this->subject($request->string('subject_type')->toString(), $request->string('subject')->toString());
        $this->authorize('update', $subject);

        $activity = $log->handle($subject, $request->safe()->except(['subject_type', 'subject']), $request->user(), $cycle->id());
        $activity->load(['user:id,name', 'callResult:id,name']);

        return response()->json(['entry' => $this->entry($activity)], 201);
    }

    private function subject(string $type, string $key): Model
    {
        try {
            return $this->subjects->resolve($type, $key);
        } catch (ModelNotFoundException) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function entry(Activity $a): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type->value,
            'type_label' => $a->type->label(),
            'direction' => $a->direction,
            'title' => $a->title,
            'body' => $a->body,
            'result' => $a->callResult?->name,
            'duration_sec' => $a->duration_sec,
            'occurred_at' => $a->occurred_at->toIso8601String(),
            'user_name' => $a->user?->name,
            'on_case' => $a->subject_type === 'admission_case',
            'meta' => $a->meta,
        ];
    }
}
