<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Actions\MergeApplicants;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\DuplicateFinder;
use App\Domain\Shared\Services\SensitiveData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\MergeApplicantsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Flow F6: /applicants/duplicates lists possible duplicates, /applicants/merge/{keep}/{merge} compares two records
 * field by field, POST /applicants/merge does the merge (MergeApplicants). Needs applicant.merge, and both records
 * must be inside the user's data scope.
 */
class ApplicantMergeController extends Controller
{
    /** field => label, in display order */
    private const LABELS = [
        'full_name' => 'Họ và tên', 'gender' => 'Giới tính', 'dob' => 'Ngày sinh', 'phone' => 'Điện thoại', 'email' => 'Email',
        'citizen_id' => 'CCCD', 'zalo_id' => 'Zalo', 'facebook_id' => 'Facebook', 'province_id' => 'Tỉnh/thành phố', 'ward_id' => 'Phường/xã',
        'address' => 'Địa chỉ', 'high_school_id' => 'Trường THPT', 'class_name' => 'Lớp', 'current_grade' => 'Khối',
        'graduation_year' => 'Năm tốt nghiệp', 'priority_area_id' => 'Khu vực ưu tiên', 'priority_group_id' => 'Đối tượng ưu tiên',
    ];

    public function __construct(private readonly SensitiveData $sensitive) {}

    public function duplicates(Request $request, DuplicateFinder $finder): Response
    {
        $this->authorize('merge', Applicant::class);

        $groups = collect($finder->groups())->map(fn (array $g) => [
            'key' => $g['key'],
            'reason' => $g['reason'],
            'reason_label' => $g['reason_label'],
            'applicants' => $g['applicants']->map(fn (Applicant $a) => $this->summary($a, $request))->values()->all(),
        ])->values()->all();

        return Inertia::render('Applicants/Duplicates', ['groups' => $groups]);
    }

    public function compare(Request $request, string $keep, string $merge): Response
    {
        $this->authorize('merge', Applicant::class);

        $left = $this->find($keep);
        $right = $this->find($merge);
        abort_if($left->is($right), 422, 'Chọn hai thí sinh khác nhau.');

        $fields = collect(self::LABELS)->map(fn (string $label, string $field) => [
            'key' => $field,
            'label' => $label,
            'keep' => $this->display($left, $field, $request),
            'merge' => $this->display($right, $field, $request),
            'differs' => $this->raw($left, $field) !== $this->raw($right, $field),
        ])->values()->all();

        return Inertia::render('Applicants/Merge', [
            'keep' => $this->summary($left, $request),
            'merge' => $this->summary($right, $request),
            'fields' => $fields,
        ]);
    }

    public function store(MergeApplicantsRequest $request, MergeApplicants $merge): RedirectResponse
    {
        $keep = $this->find($request->string('keep')->toString());
        $duplicate = $this->find($request->string('merge')->toString());

        $merge->handle($keep, $duplicate, $request->input('choices', []), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Đã gộp {$duplicate->full_name} vào {$keep->full_name}."]);

        return redirect("/applicants/{$keep->uuid}");
    }

    private function find(string $uuid): Applicant
    {
        return Applicant::query()->whereNull('merged_into_id')->where('uuid', $uuid)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function summary(Applicant $a, Request $request): array
    {
        $cases = AdmissionCase::query()->with(['cycle:id,name', 'stage:id,name'])->where('applicant_id', $a->id)->get();

        return [
            'uuid' => $a->uuid,
            'code' => $a->code,
            'full_name' => $a->full_name,
            'phone' => $this->sensitive->phone($a->phone, $request->user()),
            'email' => $a->email,
            'high_school_name' => $a->highSchool()->first()?->name,
            'created_at' => $a->created_at?->toIso8601String(),
            'guardians' => $a->guardians()->count(),
            'scores' => $a->scores()->count(),
            'cases' => $cases->map(fn (AdmissionCase $c) => ['cycle' => $c->cycle?->name, 'stage' => $c->stage?->name])->all(),
        ];
    }

    private function raw(Applicant $a, string $field): mixed
    {
        $value = $a->getAttribute($field);

        return $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
    }

    private function display(Applicant $a, string $field, Request $request): ?string
    {
        $value = $this->raw($a, $field);

        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'gender' => $a->gender?->label(),
            'phone' => $this->sensitive->phone((string) $value, $request->user()),
            'citizen_id' => $this->sensitive->citizenId((string) $value, $request->user()),
            'province_id' => $a->province()->first()?->name,
            'ward_id' => $a->ward()->first()?->name,
            'high_school_id' => $a->highSchool()->first()?->name,
            'priority_area_id' => $a->priorityArea()->first()?->code,
            'priority_group_id' => $a->priorityGroup()->first()?->code,
            default => (string) $value,
        };
    }
}
