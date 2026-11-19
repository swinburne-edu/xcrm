<?php

namespace App\Http\Controllers\HighSchool;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\HighSchool\Actions\DeleteHighSchool;
use App\Domain\HighSchool\Actions\ImportHighSchools;
use App\Domain\HighSchool\Actions\SaveHighSchool;
use App\Domain\HighSchool\Enums\ContactRole;
use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolTier;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\HighSchool\Services\SchoolStatistics;
use App\Domain\Platform\Actions\SaveCustomData;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Platform\Services\SavedViews;
use App\Domain\Platform\Services\CustomFields;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\HighSchool\HighSchoolImportRequest;
use App\Http\Requests\HighSchool\HighSchoolRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /high-schools: list, create/edit, import from Excel, and the detail page (contacts tab;
 * applicants and timeline tabs fill in with later modules).
 */
class HighSchoolController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HighSchool::class);

        $schools = ListQuery::for(HighSchool::query()->with(['province:id,name', 'owner:id,name'])->withCount('contacts'), $request)
            ->filters(
                Filter::search('q', ['name', 'code']),
                Filter::exact('province_id'),
                Filter::exact('tier'),
                Filter::exact('partnership_status'),
                Filter::exact('type'),
                Filter::exact('owner_id'),
                ...app(CustomFields::class)->filters(CustomFieldObject::HighSchools),
            )
            ->sorts(['name', 'code', 'tier', 'grade12_students', 'created_at'])
            ->defaultSort('name')
            ->toProps(fn (HighSchool $s) => $this->row($s) + ['contacts_count' => $s->contacts_count]);

        return Inertia::render('HighSchools/Index', [
            'schools' => $schools,
            'saved_views' => app(SavedViews::class)->for(SavedViewObject::HighSchools, $request->user()),
            'options' => $this->options(),
            'can' => [
                'create' => $request->user()->can('create', HighSchool::class),
                'import' => $request->user()->can('import', HighSchool::class),
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $school = HighSchool::query()->with(['province:id,name', 'ward:id,name', 'owner:id,name', 'priorityArea:id,code', 'contacts'])->findOrFail($id);
        $this->authorize('view', $school);

        $user = $request->user();

        return Inertia::render('HighSchools/Show', [
            'custom_fields' => app(CustomFields::class)->panel(CustomFieldObject::HighSchools, $school),
            'school' => $this->row($school) + [
                'custom_data' => $school->custom_data ?? (object) [],
                'ward_id' => $school->ward_id,
                'ward_name' => $school->ward?->name,
                'address' => $school->address,
                'priority_area_id' => $school->priority_area_id,
                'priority_area_code' => $school->priorityArea?->code,
                'lat' => $school->lat === null ? null : (float) $school->lat,
                'lng' => $school->lng === null ? null : (float) $school->lng,
                'grade12_classes' => $school->grade12_classes,
                'strong_combinations' => $school->strong_combinations ?? [],
                'mou_signed_at' => $school->mou_signed_at?->format('Y-m-d'),
                'phone' => $school->phone,
                'email' => $school->email,
                'website' => $school->website,
                'note' => $school->note,
            ],
            'applicants' => [
                'total' => Applicant::query()->where('high_school_id', $school->id)->count(),
                'items' => Applicant::query()->where('high_school_id', $school->id)->orderByDesc('id')->limit(30)->get(['id', 'uuid', 'code', 'full_name', 'graduation_year', 'class_name'])
                    ->map(fn (Applicant $a) => ['uuid' => $a->uuid, 'code' => $a->code, 'full_name' => $a->full_name, 'graduation_year' => $a->graduation_year, 'class_name' => $a->class_name])->all(),
            ],
            'activities' => $user->can('viewAny', SchoolActivity::class)
                ? SchoolActivity::query()->with('staff:id,name')->withCount('attendees')->where('high_school_id', $school->id)->orderByDesc('start_at')->limit(50)->get()->map(fn (SchoolActivity $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'type_label' => $a->type->label(),
                    'status' => $a->status->value,
                    'status_label' => $a->status->label(),
                    'status_tone' => $a->status->tone(),
                    'start_at' => $a->start_at?->toIso8601String(),
                    'location' => $a->location,
                    'checkins' => (int) $a->attendees_count,
                    'actual_attendees' => $a->actual_attendees,
                ])->all()
                : [],
            'stats' => app(SchoolStatistics::class)->forSchool($school),
            'contacts' => $school->contacts->map(fn (SchoolContact $c) => [
                'id' => $c->id,
                'full_name' => $c->full_name,
                'role' => $c->role?->value,
                'role_label' => $c->role?->label(),
                'homeroom_class' => $c->homeroom_class,
                'phone' => $c->phone,
                'email' => $c->email,
                'zalo' => $c->zalo,
                'relationship_level' => $c->relationship_level,
                'is_primary' => $c->is_primary,
                'note' => $c->note,
            ])->all(),
            'options' => $this->options() + ['contactRoles' => self::enumOptions(ContactRole::class)],
            'can' => [
                'update' => $user->can('update', $school),
                'delete' => $user->can('delete', $school),
                'activities' => ['create' => $user->can('create', SchoolActivity::class)],
                'contacts' => [
                    'create' => $user->can('create', SchoolContact::class),
                    'update' => $user->can('update', new SchoolContact),
                    'delete' => $user->can('delete', new SchoolContact),
                ],
            ],
        ]);
    }

    public function store(HighSchoolRequest $request, SaveHighSchool $save): RedirectResponse
    {
        $school = $save->handle(null, $request->validated());
        $this->saveCustom($school, $request);

        return $this->toast("Đã thêm trường {$school->name}.", "/high-schools/{$school->id}");
    }

    public function update(HighSchoolRequest $request, int $id, SaveHighSchool $save): RedirectResponse
    {
        $school = HighSchool::query()->findOrFail($id);
        $save->handle($school, $request->validated());
        $this->saveCustom($school, $request);

        return $this->toast("Đã cập nhật trường {$school->name}.");
    }

    public function destroy(int $id, DeleteHighSchool $delete): RedirectResponse
    {
        $school = HighSchool::query()->findOrFail($id);
        $this->authorize('delete', $school);

        $delete->handle($school);

        return $this->toast("Đã xoá trường {$school->name}.", '/high-schools');
    }

    /** Excel/CSV import; with dry_run=1 only reports what would happen. */
    public function import(HighSchoolImportRequest $request, ImportHighSchools $import): RedirectResponse
    {
        $result = $import->handle($request->file('file'), $request->boolean('dry_run'));

        Inertia::flash('import', $result);

        return $this->toast(
            $result['dry_run']
                ? "Kiểm tra xong: {$result['created']} trường mới, {$result['updated']} cập nhật, {$result['skipped']} dòng lỗi."
                : "Đã nhập: {$result['created']} trường mới, {$result['updated']} cập nhật, {$result['skipped']} dòng bỏ qua.",
        );
    }

    /** @return array<string, mixed> */
    private function row(HighSchool $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'type' => $s->type?->value,
            'type_label' => $s->type?->label(),
            'province_id' => $s->province_id,
            'province_name' => $s->province?->name,
            'tier' => $s->tier?->value,
            'partnership_status' => $s->partnership_status->value,
            'partnership_label' => $s->partnership_status->label(),
            'grade12_students' => $s->grade12_students,
            'owner_id' => $s->owner_id,
            'owner_name' => $s->owner?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'types' => self::enumOptions(SchoolType::class),
            'tiers' => self::enumOptions(SchoolTier::class),
            'partnership' => self::enumOptions(PartnershipStatus::class),
            'priorityAreas' => PriorityArea::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (PriorityArea $a) => ['value' => (string) $a->id, 'label' => trim($a->code.' '.$a->name)])->all(),
            'combinations' => SubjectCombination::query()->where('is_active', true)->orderBy('code')->pluck('code')->all(),
            'customFields' => app(CustomFields::class)->fields(CustomFieldObject::HighSchools),
            'customFilters' => app(CustomFields::class)->filterDefinitions(CustomFieldObject::HighSchools),
        ];
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    private static function enumOptions(string $enum): array
    {
        return array_map(fn ($case) => ['value' => (string) $case->value, 'label' => $case->label()], $enum::cases());
    }

    private function saveCustom(HighSchool $school, HighSchoolRequest $request): void
    {
        if ($request->has('custom')) {
            app(SaveCustomData::class)->handle($school, CustomFieldObject::HighSchools, (array) $request->validated('custom', []));
        }
    }

    private function toast(string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/high-schools');
    }
}
