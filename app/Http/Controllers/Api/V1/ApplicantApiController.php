<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Services\ApiPresenter;
use App\Domain\Applicant\Actions\UpdateApplicantField;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\ApplicantFilters;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Shared\Services\SensitiveData;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * REST: /api/v1/applicants. Same permissions and data scope as the screens (the token acts as its user).
 *  GET list (filters: the list filters, ?per_page=), GET one, POST create or find (duplicate check by phone / email),
 *  PATCH update some fields.
 */
class ApplicantApiController extends Controller
{
    private const PATCHABLE = ['full_name', 'phone', 'email', 'high_school_id', 'province_id', 'graduation_year'];

    public function __construct(private readonly ApiPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Applicant::class);

        $page = ListQuery::for(Applicant::query()->whereNull('merged_into_id')->with(['province:id,name', 'highSchool:id,name']), $request)
            ->filters(...ApplicantFilters::all())->sorts(['full_name', 'created_at', 'updated_at'])->defaultSort('-created_at')->paginate();

        return response()->json($this->presenter->page($page, fn (Applicant $a) => $this->presenter->applicant($a, $request->user())));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $applicant = Applicant::query()->with(['province:id,name', 'highSchool:id,name'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $applicant);

        return response()->json(['data' => $this->presenter->applicant($applicant, $request->user())]);
    }

    public function store(Request $request, IntakeLead $intake): JsonResponse
    {
        $this->authorize('create', Applicant::class);
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'between:2000,2100'],
            'province' => ['nullable', 'string', 'max:100'],
            'high_school' => ['nullable', 'string', 'max:255'],
            'consent' => ['boolean'],
        ]);

        if (blank($data['phone'] ?? null) && blank($data['email'] ?? null)) {
            throw ValidationException::withMessages(['phone' => 'Cần ít nhất số điện thoại hoặc email.']);
        }

        $source = LeadSource::query()->where('code', 'api')->value('id') ?? LeadSource::query()->where('code', 'website')->value('id');
        $result = $intake->handle($request->user()->organization_id, $data, ['source_id' => $source, 'label' => 'Tạo qua API']);
        $applicant = $result['applicant']->load(['province:id,name', 'highSchool:id,name']);

        return response()->json(['data' => $this->presenter->applicant($applicant, $request->user()), 'created' => $result['created']], $result['created'] ? 201 : 200);
    }

    public function update(Request $request, string $uuid, UpdateApplicantField $update): JsonResponse
    {
        $applicant = Applicant::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $applicant);
        $data = $request->validate(['full_name' => ['sometimes', 'string', 'max:255'], 'phone' => ['sometimes', 'nullable', 'string', 'max:20'], 'email' => ['sometimes', 'nullable', 'email'], 'high_school_id' => ['sometimes', 'nullable', 'integer'], 'province_id' => ['sometimes', 'nullable', 'integer'], 'graduation_year' => ['sometimes', 'nullable', 'integer', 'between:2000,2100']]);

        abort_if(array_key_exists('phone', $data) && ! app(SensitiveData::class)->canView($request->user()), 403, 'Không có quyền sửa số điện thoại.');

        foreach (array_intersect_key($data, array_flip(self::PATCHABLE)) as $field => $value) {
            $applicant = $update->handle($applicant, $field, $value);
        }

        return response()->json(['data' => $this->presenter->applicant($applicant->load(['province:id,name', 'highSchool:id,name']), $request->user())]);
    }
}
