<?php

namespace App\Domain\Applicant\Services;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Support\Str;

/**
 * Duplicate check for candidates (docs/04-database.md §5): by normalized phone, then lower-case email,
 * then citizen id hash, inside the organization. Merged applicants (merged_into_id) are ignored.
 * The data scope is deliberately NOT applied: a duplicate hidden from the user must still be found
 * (the caller decides what to reveal, see matches()).
 */
class ApplicantDeduplicator
{
    /** Order of the checks. */
    public const FIELDS = ['phone', 'email', 'citizen_id'];

    public function __construct(private readonly PhoneNormalizer $phones) {}

    /**
     * The existing applicant a new record would duplicate, or null.
     *
     * @param  array<string, mixed>  $data  phone, email, citizen_id
     */
    public function find(array $data, ?int $exceptId = null): ?Applicant
    {
        return $this->matches($data, $exceptId)[0]['applicant'] ?? null;
    }

    /**
     * Every match, one per field that collides, in check order.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{field: string, applicant: Applicant}>
     */
    public function matches(array $data, ?int $exceptId = null): array
    {
        $found = [];

        foreach (self::FIELDS as $field) {
            $applicant = match ($field) {
                'phone' => $this->byColumn('phone_normalized', $this->phones->normalize($this->text($data['phone'] ?? null)), $exceptId),
                'email' => $this->byColumn('email', ($email = $this->text($data['email'] ?? null)) === null ? null : Str::lower($email), $exceptId),
                'citizen_id' => $this->byColumn('citizen_id_hash', Applicant::hashCitizenId($this->text($data['citizen_id'] ?? null)), $exceptId),
            };

            if ($applicant !== null) {
                $found[] = ['field' => $field, 'applicant' => $applicant];
            }
        }

        return $found;
    }

    private function byColumn(string $column, ?string $value, ?int $exceptId): ?Applicant
    {
        if ($value === null) {
            return null;
        }

        return Applicant::query()->withoutDataScope()->whereNull('merged_into_id')
            ->where($column, $value)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('id')->first();
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
