<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Catalog\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Runs one operation over many cases (assign, move to a stage, tag, untag). Each case is checked against
 * the user's permissions and the case rules on its own: failures are collected, the rest still go through.
 */
class BulkCaseAction
{
    public const MAX_CASES = 200;

    public function __construct(
        private readonly AssignCase $assign,
        private readonly MoveCaseStage $move,
    ) {}

    /**
     * @param  list<string>  $uuids
     * @param  array<string, mixed>  $params  owner_id (assign, null = pool), stage_id / lost_reason_id / lost_note (move), tag_id (tag, untag)
     * @return array{done: int, failed: list<array{uuid: string, name: string, message: string}>}
     */
    public function handle(string $action, array $uuids, array $params, User $by): array
    {
        $cases = AdmissionCase::query()->with('applicant:id,full_name')->whereIn('uuid', array_slice($uuids, 0, self::MAX_CASES))->get();
        $owner = $action === 'assign' && ($params['owner_id'] ?? null) !== null ? User::query()->where('organization_id', $by->organization_id)->find($params['owner_id']) : null;
        $stage = $action === 'move' ? PipelineStage::query()->find($params['stage_id'] ?? 0) : null;
        $tag = in_array($action, ['tag', 'untag'], true) ? Tag::query()->find($params['tag_id'] ?? 0) : null;

        $result = ['done' => 0, 'failed' => []];

        foreach ($cases as $case) {
            try {
                match ($action) {
                    'assign' => $this->assignOne($case, $owner, $params, $by),
                    'move' => $this->moveOne($case, $stage, $params, $by),
                    'tag', 'untag' => $this->tagOne($case, $tag, $action, $by),
                    default => throw ValidationException::withMessages(['action' => 'Thao tác không hợp lệ.']),
                };
                $result['done']++;
            } catch (ValidationException $e) {
                $result['failed'][] = ['uuid' => $case->uuid, 'name' => $case->applicant?->full_name ?? $case->uuid, 'message' => collect($e->errors())->flatten()->first() ?? 'Không thực hiện được.'];
            }
        }

        $missing = count($uuids) - $cases->count();

        for ($i = 0; $i < $missing; $i++) {
            $result['failed'][] = ['uuid' => '', 'name' => 'Hồ sơ', 'message' => 'Không tìm thấy hoặc ngoài phạm vi dữ liệu của bạn.'];
        }

        return $result;
    }

    /** @param  array<string, mixed>  $params */
    private function assignOne(AdmissionCase $case, ?User $owner, array $params, User $by): void
    {
        $this->authorize('assign', $case, 'Bạn không có quyền giao hồ sơ này.');

        if (($params['owner_id'] ?? null) !== null && $owner === null) {
            throw ValidationException::withMessages(['owner_id' => 'Người phụ trách không hợp lệ.']);
        }

        $this->assign->handle($case, $owner, $params['team_id'] ?? null, $by);
    }

    /** @param  array<string, mixed>  $params */
    private function moveOne(AdmissionCase $case, ?PipelineStage $stage, array $params, User $by): void
    {
        $this->authorize('update', $case, 'Bạn không có quyền sửa hồ sơ này.');

        if ($stage === null) {
            throw ValidationException::withMessages(['stage_id' => 'Giai đoạn không hợp lệ.']);
        }

        $this->move->handle($case, $stage, $by, ['lost_reason_id' => $params['lost_reason_id'] ?? null, 'lost_note' => $params['lost_note'] ?? null]);
    }

    private function tagOne(AdmissionCase $case, ?Tag $tag, string $action, User $by): void
    {
        $this->authorize('update', $case, 'Bạn không có quyền sửa hồ sơ này.');

        if ($tag === null) {
            throw ValidationException::withMessages(['tag_id' => 'Tag không hợp lệ.']);
        }

        $action === 'tag' ? $case->tags()->syncWithoutDetaching([$tag->id]) : $case->tags()->detach($tag->id);
    }

    private function authorize(string $ability, AdmissionCase $case, string $message): void
    {
        if (! Gate::allows($ability, $case)) {
            throw ValidationException::withMessages(['permission' => $message]);
        }
    }
}
