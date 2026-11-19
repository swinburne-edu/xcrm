<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Services\ApplicationPanel;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Applicant\Actions\SaveApplicant;
use App\Domain\Applicant\Enums\CertificateType;
use App\Domain\Applicant\Enums\Gender;
use App\Domain\Applicant\Enums\GuardianRelationship;
use App\Domain\Applicant\Enums\ScoreType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantCertificate;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Applicant\Models\Guardian;
use App\Domain\Applicant\Services\ApplicantDeduplicator;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\PriorityGroup;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Platform\Actions\SaveCustomData;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Platform\Services\SavedViews;
use App\Domain\Platform\Services\CustomFields;
use App\Domain\Shared\Services\SensitiveData;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\ApplicantRequest;
use App\Http\Requests\Applicant\ScoresRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /applicants: list, create/edit with a duplicate check, and the detail page (info, guardians,
 * scores grid, certificates; timeline / applications / documents / appointments arrive with later tasks).
 * Phone and citizen id are masked for users without applicant.view_sensitive.
 */
class ApplicantController extends Controller
{
    public function __construct(private readonly SensitiveData $sensitive) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Applicant::class);

        $applicants = ListQuery::for(
            Applicant::query()->whereNull('merged_into_id')->with(['province:id,name', 'highSchool:id,name']),
            $request,
        )
            ->filters(
                Filter::search('q', ['full_name', 'code', 'email', 'phone', 'phone_normalized']),
                Filter::exact('province_id'),
                Filter::exact('high_school_id'),
                Filter::exact('graduation_year'),
                Filter::exact('current_grade'),
                Filter::exact('first_source_id'),
                Filter::exact('first_campaign_id'),
                ...app(CustomFields::class)->filters(CustomFieldObject::Applicants),
            )
            ->sorts(['full_name', 'code', 'graduation_year', 'created_at'])
            ->defaultSort('-created_at')
            ->toProps(fn (Applicant $a) => $this->row($a, $request));

        return Inertia::render('Applicants/Index', [
            'applicants' => $applicants,
            'saved_views' => app(SavedViews::class)->for(SavedViewObject::Applicants, $request->user()),
            'options' => $this->options(),
            'can' => ['create' => $request->user()->can('create', Applicant::class), 'import' => $request->user()->can('import', Applicant::class)],
        ]);
    }

    public function show(Request $request, string $uuid, ApplicationPanel $panel, CurrentCycle $currentCycle, CustomFields $customFields): Response
    {
        $applicant = Applicant::query()->where('uuid', $uuid)
            ->with(['province:id,name', 'ward:id,name', 'highSchool:id,name', 'priorityArea:id,code', 'priorityGroup:id,code', 'firstSource:id,name', 'firstCampaign:id,name', 'referrer:id,name', 'guardians', 'scores', 'certificates'])
            ->firstOrFail();
        $this->authorize('view', $applicant);

        $user = $request->user();

        return Inertia::render('Applicants/Show', [
            'applicant' => $this->row($applicant, $request) + [
                'gender' => $applicant->gender?->value,
                'gender_label' => $applicant->gender?->label(),
                'dob' => $applicant->dob?->format('Y-m-d'),
                'citizen_id' => $this->sensitive->citizenId($applicant->citizen_id, $user, $applicant),
                'sensitive_masked' => ! $this->sensitive->canView($user),
                'custom_data' => $applicant->custom_data ?? (object) [],
                'zalo_id' => $applicant->zalo_id,
                'facebook_id' => $applicant->facebook_id,
                'province_id' => $applicant->province_id,
                'ward_id' => $applicant->ward_id,
                'ward_name' => $applicant->ward?->name,
                'address' => $applicant->address,
                'class_name' => $applicant->class_name,
                'priority_area_id' => $applicant->priority_area_id,
                'priority_area_code' => $applicant->priorityArea?->code,
                'priority_group_id' => $applicant->priority_group_id,
                'priority_group_code' => $applicant->priorityGroup?->code,
                'first_source_id' => $applicant->first_source_id,
                'first_source_name' => $applicant->firstSource?->name,
                'first_campaign_id' => $applicant->first_campaign_id,
                'first_campaign_name' => $applicant->firstCampaign?->name,
                'referred_by_user_id' => $applicant->referred_by_user_id,
                'referrer_name' => $applicant->referrer?->name,
                'consent_marketing' => $applicant->consent_marketing,
            ],
            'custom_fields' => $customFields->panel(CustomFieldObject::Applicants, $applicant),
            'guardians' => $applicant->guardians->map(fn (Guardian $g) => [
                'id' => $g->id,
                'full_name' => $g->full_name,
                'phone' => $this->sensitive->phone($g->phone, $user),
                'email' => $g->email,
                'zalo_id' => $g->zalo_id,
                'occupation' => $g->occupation,
                'note' => $g->note,
                'relationship' => $g->pivot->relationship,
                'relationship_label' => GuardianRelationship::tryFrom((string) $g->pivot->relationship)?->label(),
                'is_decision_maker' => (bool) $g->pivot->is_decision_maker,
                'is_primary_contact' => (bool) $g->pivot->is_primary_contact,
            ])->all(),
            'cases' => AdmissionCase::query()->with(['cycle:id,name', 'stage:id,name,color', 'owner:id,name', 'source:id,name'])->where('applicant_id', $applicant->id)->orderByDesc('cycle_id')->get()->map(fn (AdmissionCase $c) => [
                'uuid' => $c->uuid,
                'cycle' => $c->cycle?->name,
                'stage' => $c->stage?->name,
                'stage_color' => $c->stage?->color,
                'status' => $c->status->value,
                'owner' => $c->owner?->name,
                'source' => $c->source?->name,
                'next_action_at' => $c->next_action_at?->toIso8601String(),
                'last_activity_at' => $c->last_activity_at?->toIso8601String(),
            ])->all(),
            'scores' => $applicant->scores->map(fn (ApplicantScore $s) => [
                'score_type' => $s->score_type->value,
                'subject_id' => $s->subject_id,
                'term' => $s->term,
                'exam_name' => $s->exam_name,
                'score' => $s->score === null ? null : (float) $s->score,
                'max_score' => (float) $s->max_score,
                'year' => $s->year,
                'verified' => $s->verified,
            ])->all(),
            'certificates' => $applicant->certificates->map(fn (ApplicantCertificate $c) => [
                'id' => $c->id,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'score' => $c->score,
                'issued_at' => $c->issued_at?->format('Y-m-d'),
                'expires_at' => $c->expires_at?->format('Y-m-d'),
                'verified' => $c->verified,
            ])->all(),
            'subjects' => Subject::query()->orderByDesc('is_core')->orderBy('id')->get(['id', 'code', 'name', 'is_core'])
                ->map(fn (Subject $s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'is_core' => $s->is_core])->all(),
            'application_panel' => $panel->applications($applicant, $currentCycle->get(), $user),
            'document_panel' => $panel->documents($applicant, $user),
            'options' => $this->options(),
            'can' => [
                'update' => $user->can('update', $applicant),
                'delete' => $user->can('delete', $applicant),
            ],
        ]);
    }

    public function store(ApplicantRequest $request, SaveApplicant $save): RedirectResponse
    {
        $applicant = $save->handle(null, $request->validated(), $request->user());
        $this->saveCustom($applicant, $request);

        return $this->toast("Đã thêm thí sinh {$applicant->full_name} ({$applicant->code}).", "/applicants/{$applicant->uuid}");
    }

    public function update(ApplicantRequest $request, string $uuid, SaveApplicant $save): RedirectResponse
    {
        $applicant = $request->applicant() ?? abort(404);
        $save->handle($applicant, $request->validated(), $request->user());
        $this->saveCustom($applicant, $request);

        return $this->toast("Đã cập nhật thí sinh {$applicant->full_name}.");
    }

    /** Soft delete (rule R9: applicants are never removed for good). */
    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $applicant = Applicant::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $applicant);

        $applicant->delete();
        activity()->causedBy($request->user())->performedOn($applicant)->log('Xoá thí sinh');

        return $this->toast("Đã xoá thí sinh {$applicant->full_name}.", '/applicants');
    }

    /**
     * Realtime duplicate warning for the form: which fields collide with an existing applicant.
     * An applicant outside the user's data scope is reported without identity.
     */
    public function checkDuplicate(Request $request, ApplicantDeduplicator $deduplicator): JsonResponse
    {
        $this->authorize('viewAny', Applicant::class);

        $except = $request->filled('except') ? Applicant::query()->where('uuid', $request->query('except'))->value('id') : null;
        $labels = ['phone' => 'Số điện thoại', 'email' => 'Email', 'citizen_id' => 'Số CCCD'];

        $matches = collect($deduplicator->matches($request->only(['phone', 'email', 'citizen_id']), $except))->map(function (array $match) use ($request, $labels): array {
            $applicant = $match['applicant'];
            $visible = (bool) $request->user()->can('view', $applicant);

            return [
                'field' => $match['field'],
                'label' => $labels[$match['field']],
                'visible' => $visible,
                'uuid' => $visible ? $applicant->uuid : null,
                'code' => $visible ? $applicant->code : null,
                'full_name' => $visible ? $applicant->full_name : null,
            ];
        })->all();

        return response()->json(['matches' => $matches]);
    }

    /** @return array<string, mixed> */
    private function row(Applicant $a, Request $request): array
    {
        return [
            'id' => $a->uuid,
            'uuid' => $a->uuid,
            'code' => $a->code,
            'full_name' => $a->full_name,
            'phone' => $this->sensitive->phone($a->phone, $request->user()),
            'email' => $a->email,
            'province_name' => $a->province?->name,
            'high_school_name' => $a->highSchool?->name,
            'high_school_id' => $a->high_school_id,
            'class_name' => $a->class_name,
            'current_grade' => $a->current_grade,
            'graduation_year' => $a->graduation_year,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        $enum = fn (string $class) => array_map(fn ($c) => ['value' => (string) $c->value, 'label' => $c->label()], $class::cases());

        return [
            'genders' => $enum(Gender::class),
            'relationships' => $enum(GuardianRelationship::class),
            'scoreTypes' => $enum(ScoreType::class),
            'certificateTypes' => $enum(CertificateType::class),
            'terms' => ScoresRequest::TERMS,
            'sources' => LeadSource::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->all(),
            'priorityGroups' => PriorityGroup::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($g) => ['value' => (string) $g->id, 'label' => trim($g->code.' '.$g->name)])->all(),
            'priorityAreas' => PriorityArea::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($a) => ['value' => (string) $a->id, 'label' => trim($a->code.' '.$a->name)])->all(),
            'customFields' => app(CustomFields::class)->fields(CustomFieldObject::Applicants),
            'customFilters' => app(CustomFields::class)->filterDefinitions(CustomFieldObject::Applicants),
        ];
    }

    private function saveCustom(Applicant $applicant, ApplicantRequest $request): void
    {
        if ($request->has('custom')) {
            app(SaveCustomData::class)->handle($applicant, CustomFieldObject::Applicants, (array) $request->validated('custom', []));
        }
    }

    private function toast(string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/applicants');
    }
}
