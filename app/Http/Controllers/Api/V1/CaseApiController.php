<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Api\Services\ApiPresenter;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST: /api/v1/cases (admission cases). List with filters stage_id, status, owner_id, cycle_id, source_id, campaign_id;
 * one case; move a case to another stage. Same permissions and data scope as the screens.
 */
class CaseApiController extends Controller
{
    private const WITH = ['applicant:id,uuid', 'cycle:id,name', 'stage:id,code,name', 'owner:id,name'];

    public function __construct(private readonly ApiPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdmissionCase::class);

        $page = ListQuery::for(AdmissionCase::query()->with(self::WITH), $request)
            ->filters(Filter::exact('stage_id'), Filter::exact('status'), Filter::exact('owner_id'), Filter::exact('cycle_id'), Filter::exact('source_id'), Filter::exact('campaign_id'))
            ->sorts(['created_at', 'last_activity_at', 'score'])->defaultSort('-created_at')->paginate();

        return response()->json($this->presenter->page($page, fn (AdmissionCase $c) => $this->presenter->case($c)));
    }

    public function show(string $uuid): JsonResponse
    {
        $case = AdmissionCase::query()->with(self::WITH)->where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $case);

        return response()->json(['data' => $this->presenter->case($case)]);
    }

    public function stage(Request $request, string $uuid, MoveCaseStage $move): JsonResponse
    {
        $case = AdmissionCase::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $case);
        $data = $request->validate(['stage_id' => ['required', 'integer'], 'lost_reason_id' => ['nullable', 'integer']]);
        $stage = PipelineStage::query()->where('pipeline_id', $case->pipeline_id)->findOrFail($data['stage_id']);

        $case = $move->handle($case, $stage, $request->user(), array_filter(['lost_reason_id' => $data['lost_reason_id'] ?? null]));

        return response()->json(['data' => $this->presenter->case($case->load(self::WITH))]);
    }
}
