<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Admission\Actions\SaveApplication;
use App\Domain\Admission\Actions\TransitionApplication;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Api\Services\ApiPresenter;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST: /api/v1/applications (nguyen vong). List with filters status, major_id, round_id, cycle_method_id; one; add a
 * preference to a case; run a step (submit, eligible, ineligible, conditional, admit, reject, confirm, withdraw).
 * Steps need application.update (applicant side) or application.review (registrar decisions), as on the screens.
 */
class ApplicationApiController extends Controller
{
    private const WITH = ['case:id,uuid', 'major:id,moet_code,admission_code,name', 'cycleMethod.method:id,name', 'combination:id,code', 'round:id,name'];

    public function __construct(private readonly ApiPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Application::class);

        $page = ListQuery::for(Application::query()->whereHas('case')->with(self::WITH), $request)
            ->filters(Filter::exact('status'), Filter::exact('major_id'), Filter::exact('round_id'), Filter::exact('cycle_method_id'))
            ->sorts(['created_at', 'preference_order', 'final_score'])->defaultSort('-created_at')->paginate();

        return response()->json($this->presenter->page($page, fn (Application $a) => $this->presenter->application($a)));
    }

    public function show(int $id): JsonResponse
    {
        $application = Application::query()->whereHas('case')->with(self::WITH)->findOrFail($id);
        $this->authorize('view', $application);

        return response()->json(['data' => $this->presenter->application($application)]);
    }

    public function store(Request $request, string $uuid, SaveApplication $save): JsonResponse
    {
        $case = AdmissionCase::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('create', Application::class);
        $data = $request->validate([
            'major_id' => ['required', 'integer'], 'cycle_method_id' => ['required', 'integer'], 'combination_id' => ['nullable', 'integer'],
            'round_id' => ['required', 'integer'], 'preference_order' => ['nullable', 'integer', 'between:1,15'],
        ]);

        $application = $save->handle($case, null, $data, $request->user());

        return response()->json(['data' => $this->presenter->application($application->load(self::WITH))], 201);
    }

    public function transition(Request $request, int $id, string $action, TransitionApplication $transition): JsonResponse
    {
        abort_unless(isset(TransitionApplication::ACTIONS[$action]), 404);
        $application = Application::query()->whereHas('case')->findOrFail($id);
        $this->authorize(in_array($action, TransitionApplication::REVIEW_ACTIONS, true) ? 'review' : 'update', $application);

        $application = $transition->handle($application, $action, $request->user());

        return response()->json(['data' => $this->presenter->application($application->load(self::WITH))]);
    }
}
