<?php

namespace App\Domain\Communication\Services;

use App\Domain\Applicant\Models\Applicant;
use App\Models\User;

/**
 * Fills {{variable}} placeholders. Only the variables below exist; a template using any other name is rejected when
 * it is saved (unknown()), so a typo never goes out as an empty gap.
 */
class TemplateRenderer
{
    /** variable => label shown in the template editor */
    public const VARIABLES = [
        'applicant.full_name' => 'Họ tên thí sinh',
        'applicant.first_name' => 'Tên (từ cuối của họ tên)',
        'applicant.code' => 'Mã thí sinh',
        'applicant.email' => 'Email thí sinh',
        'applicant.high_school' => 'Trường THPT',
        'applicant.graduation_year' => 'Năm tốt nghiệp',
        'organization.name' => 'Tên trường/tổ chức',
        'sender.name' => 'Người gửi',
    ];

    private const PATTERN = '/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/';

    /** @return list<string> variable names used in the text */
    public function variablesIn(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /** @return list<string> variables used in the text that do not exist */
    public function unknown(string $text): array
    {
        return array_values(array_diff($this->variablesIn($text), array_keys(self::VARIABLES)));
    }

    /** @param  array<string, string>  $values */
    public function render(string $text, array $values): string
    {
        return preg_replace_callback(self::PATTERN, fn (array $m) => $values[$m[1]] ?? '', $text) ?? $text;
    }

    /** @return array<string, string> */
    public function valuesFor(Applicant $applicant, ?User $sender = null): array
    {
        $name = trim((string) $applicant->full_name);

        return [
            'applicant.full_name' => $name,
            'applicant.first_name' => $name === '' ? '' : (string) collect(preg_split('/\s+/u', $name))->last(),
            'applicant.code' => (string) $applicant->code,
            'applicant.email' => (string) $applicant->email,
            'applicant.high_school' => (string) $applicant->highSchool?->name,
            'applicant.graduation_year' => (string) $applicant->graduation_year,
            'organization.name' => (string) $applicant->organization?->name,
            'sender.name' => (string) $sender?->name,
        ];
    }
}
