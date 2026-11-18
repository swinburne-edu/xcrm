<?php

namespace App\Domain\Intake\Actions;

use App\Domain\Activity\Actions\CaseNextAction;
use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\ApplicantDeduplicator;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Flow F3 (docs/05-luong-nghiep-vu.md): one lead from a web form, an import row or a webhook.
 *
 *  1. Phone is normalized and the lead checked against existing applicants (phone → email → citizen id).
 *  2. A duplicate does not create an applicant: the empty fields of the old record are filled in and, in the
 *     current cycle, a new touch is added to its case (the case is opened when it has none yet).
 *  3. A new applicant gets a case of the current cycle at the first stage; the owner comes from the territory rules
 *     (CreateAdmissionCase), else the case stays in the shared pool.
 *  4. A new case gets the task "Gọi lần đầu", due now + the SLA hours of its first stage.
 *
 * Works for any organization without a logged-in user (public endpoint, queue job): the organization is given.
 */
class IntakeLead
{
    public const DEFAULT_SLA_HOURS = 24;

    public function __construct(
        private readonly ApplicantDeduplicator $duplicates,
        private readonly PhoneNormalizer $phones,
        private readonly CreateAdmissionCase $createCase,
        private readonly CaseNextAction $nextAction,
        private readonly RecordActivity $record,
        private readonly CurrentOrganization $current,
    ) {}

    /**
     * @param  array<string, mixed>  $data  full_name, phone, email, gender, dob, citizen_id, province (id or name), high_school (id or name),
     *                                      graduation_year, major (id, code or name), note, consent
     * @param  array<string, mixed>  $context  source_id, campaign_id, form_id, utm (array), payload (array), label (timeline text)
     * @return array{applicant: Applicant, case: AdmissionCase|null, created: bool, case_created: bool}
     *
     * @throws ValidationException
     */
    public function handle(int $organizationId, array $data, array $context = []): array
    {
        return $this->current->run($organizationId, fn () => DB::transaction(fn () => $this->process($organizationId, $data, $context)));
    }

    /**
     * Cleans one lead without writing anything: the data as it would be stored, or the validation errors.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function clean(int $organizationId, array $data): array
    {
        return $this->current->run($organizationId, function () use ($data): array {
            $name = $this->text($data['full_name'] ?? null);
            $email = $this->text($data['email'] ?? null);
            $phoneRaw = $this->text($data['phone'] ?? null);
            $errors = [];

            if ($name === null) {
                $errors['full_name'] = 'Thiếu họ tên.';
            }

            if ($phoneRaw === null && $email === null) {
                $errors['phone'] = 'Cần số điện thoại hoặc email.';
            }

            if ($phoneRaw !== null && ! $this->phones->isValid($phoneRaw)) {
                $errors['phone'] = 'Số điện thoại không hợp lệ.';
            }

            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Email không hợp lệ.';
            }

            $year = $this->text($data['graduation_year'] ?? null);

            if ($year !== null && (! ctype_digit($year) || (int) $year < 2000 || (int) $year > 2100)) {
                $errors['graduation_year'] = 'Năm tốt nghiệp không hợp lệ.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $dob = $this->text($data['dob'] ?? null);
            $gender = $this->text($data['gender'] ?? null);

            return [
                'full_name' => $name,
                'phone' => $phoneRaw,
                'email' => $email === null ? null : Str::lower($email),
                'citizen_id' => $this->text($data['citizen_id'] ?? null),
                'gender' => $gender === null ? null : $this->gender($gender),
                'dob' => $dob === null ? null : $this->date($dob),
                'province_id' => $this->province($data['province'] ?? null),
                'high_school_id' => $this->highSchool($data['high_school'] ?? null),
                'graduation_year' => $year === null ? null : (int) $year,
                'major_id' => $this->major($data['major'] ?? null),
                'note' => $this->text($data['note'] ?? null),
                'consent' => (bool) ($data['consent'] ?? false),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @return array{applicant: Applicant, case: AdmissionCase|null, created: bool, case_created: bool}
     */
    private function process(int $organizationId, array $data, array $context): array
    {
        $clean = $this->clean($organizationId, $data);
        $existing = $this->duplicates->find($clean);

        if ($existing !== null) {
            $applicant = $this->fillGaps($existing, $clean);
            $created = false;
        } else {
            $applicant = Applicant::query()->create([
                'organization_id' => $organizationId,
                'full_name' => $clean['full_name'],
                'gender' => $clean['gender'],
                'dob' => $clean['dob'],
                'phone' => $clean['phone'],
                'email' => $clean['email'],
                'citizen_id' => $clean['citizen_id'],
                'province_id' => $clean['province_id'],
                'high_school_id' => $clean['high_school_id'],
                'graduation_year' => $clean['graduation_year'],
                'first_source_id' => $context['source_id'] ?? null,
                'first_campaign_id' => $context['campaign_id'] ?? null,
                'consent_marketing' => $clean['consent'],
                'consent_at' => $clean['consent'] ? now() : null,
            ])->refresh();
            $created = true;
        }

        $payload = array_filter(($context['payload'] ?? []) + ['note' => $clean['note']], fn ($v) => $v !== null && $v !== '');
        $utm = array_filter((array) ($context['utm'] ?? []), fn ($v) => $v !== null && $v !== '');
        $cycle = AdmissionCycle::query()->where('is_current', true)->where('status', '!=', 'closed')->orderByDesc('year')->first();

        if ($cycle === null) {
            return ['applicant' => $applicant, 'case' => null, 'created' => $created, 'case_created' => false];
        }

        $case = AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $applicant->id)->where('cycle_id', $cycle->id)->first();
        $caseCreated = $case === null;
        $label = $context['label'] ?? 'Đăng ký qua biểu mẫu';

        if ($caseCreated) {
            $case = $this->createCase->handle($applicant, $cycle, array_filter([
                'source_id' => $context['source_id'] ?? null,
                'campaign_id' => $context['campaign_id'] ?? null,
                'form_id' => $context['form_id'] ?? null,
                'school_activity_id' => $context['school_activity_id'] ?? null,
                'primary_major_id' => $clean['major_id'],
                'utm_source' => $utm['source'] ?? null,
                'utm_medium' => $utm['medium'] ?? null,
                'utm_campaign' => $utm['campaign'] ?? null,
                'utm_content' => $utm['content'] ?? null,
                'payload' => $payload ?: null,
            ], fn ($v) => $v !== null));

            $this->firstCallTask($case);
        } else {
            CaseTouch::query()->create([
                'case_id' => $case->id,
                'source_id' => $context['source_id'] ?? null,
                'campaign_id' => $context['campaign_id'] ?? null,
                'school_activity_id' => $context['school_activity_id'] ?? null,
                'form_id' => $context['form_id'] ?? null,
                'utm' => $utm ?: null,
                'payload' => $payload ?: null,
                'touched_at' => now(),
            ]);

            if ($case->primary_major_id === null && $clean['major_id'] !== null) {
                AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['primary_major_id' => $clean['major_id']]);
            }
        }

        $this->record->handle($case, ActivityType::System, [
            'title' => $caseCreated ? $label : "{$label} (đăng ký lại)",
            'body' => $clean['note'],
            'meta' => ['form_id' => $context['form_id'] ?? null, 'duplicate' => ! $created],
        ]);

        return ['applicant' => $applicant, 'case' => $case, 'created' => $created, 'case_created' => $caseCreated];
    }

    /**
     * Fills the blanks of an existing applicant with what the new lead knows; never overwrites.
     *
     * @param  array<string, mixed>  $clean
     */
    private function fillGaps(Applicant $applicant, array $clean): Applicant
    {
        foreach (['gender', 'dob', 'email', 'phone', 'province_id', 'high_school_id', 'graduation_year'] as $field) {
            if ($applicant->getAttribute($field) === null && $clean[$field] !== null) {
                $applicant->setAttribute($field, $clean[$field]);
            }
        }

        if ($applicant->isDirty()) {
            $applicant->save();
        }

        return $applicant;
    }

    private function firstCallTask(AdmissionCase $case): void
    {
        $hours = $case->stage()->value('sla_hours') ?: self::DEFAULT_SLA_HOURS;

        Task::query()->create([
            'organization_id' => $case->organization_id,
            'subject_type' => 'admission_case',
            'subject_id' => $case->id,
            'title' => 'Gọi lần đầu',
            'type' => 'call_back',
            'priority' => 'high',
            'assignee_id' => $case->owner_id,
            'due_at' => now()->addHours((int) $hours),
        ]);

        $this->nextAction->sync('admission_case', $case->id);
    }

    private function province(mixed $value): ?int
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return ctype_digit($value)
            ? Province::query()->whereKey((int) $value)->value('id')
            : Province::query()->whereRaw('LOWER(name) = ?', [Str::lower($value)])->orWhere('code', $value)->value('id');
    }

    private function highSchool(mixed $value): ?int
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return ctype_digit($value)
            ? HighSchool::query()->whereKey((int) $value)->value('id')
            : HighSchool::query()->whereRaw('LOWER(name) = ?', [Str::lower($value)])->orWhere('code', $value)->value('id');
    }

    private function major(mixed $value): ?int
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return ctype_digit($value)
            ? Major::query()->whereKey((int) $value)->value('id')
            : Major::query()->whereRaw('LOWER(name) = ?', [Str::lower($value)])->orWhere('admission_code', $value)->value('id');
    }

    private function gender(string $value): string
    {
        return match (Str::lower(Str::ascii($value))) {
            'nam', 'male', 'm' => 'male',
            'nu', 'female', 'f' => 'female',
            default => 'other',
        };
    }

    private function date(string $value): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
