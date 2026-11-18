<?php

namespace App\Http\Controllers\HighSchool;

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
use App\Domain\HighSchool\Models\SchoolContact;
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
            )
            ->sorts(['name', 'code', 'tier', 'grade12_students', 'created_at'])
            ->defaultSort('name')
            ->toProps(fn (HighSchool $s) => $this->row($s) + ['contacts_count' => $s->contacts_count]);

        return Inertia::render('HighSchools/Index', [
            'schools' => $schools,
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
            'school' => $this->row($school) + [
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

        return $this->toast("Đã thêm trường {$school->name}.", "/high-schools/{$school->id}");
    }

    public function update(HighSchoolRequest $request, int $id, SaveHighSchool $save): RedirectResponse
    {
        $school = HighSchool::query()->findOrFail($id);
        $save->handle($school, $request->validated());

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

    private function toast(string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/high-schools');
    }
}
