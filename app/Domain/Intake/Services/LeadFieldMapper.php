<?php

namespace App\Domain\Intake\Services;

use Illuminate\Support\Str;

/**
 * Turns the fields of an ad form into the lead keys IntakeLead understands (full_name, phone, email, province,
 * high_school, graduation_year, major, gender, dob, note). A field is matched by the integration mapping first
 * ({platform field name: lead key}), then by the usual names. A name split into first / last name is joined.
 * Fields that match nothing are returned as `extra` so they still land in the case payload.
 */
class LeadFieldMapper
{
    public const KEYS = ['full_name', 'phone', 'email', 'province', 'high_school', 'graduation_year', 'major', 'gender', 'dob', 'note'];

    /** lead key => label shown when choosing what a platform field means */
    public const LABELS = [
        'full_name' => 'Họ và tên', 'phone' => 'Số điện thoại', 'email' => 'Email', 'province' => 'Tỉnh/thành phố',
        'high_school' => 'Trường THPT', 'graduation_year' => 'Năm tốt nghiệp', 'major' => 'Ngành quan tâm',
        'gender' => 'Giới tính', 'dob' => 'Ngày sinh', 'note' => 'Lời nhắn',
    ];

    /** lead key => platform field names (compared lower case, no accents, separators as underscore) */
    private const SYNONYMS = [
        'full_name' => ['full_name', 'name', 'ho_ten', 'ho_va_ten', 'ten', 'your_name'],
        'phone' => ['phone', 'phone_number', 'mobile', 'sdt', 'so_dien_thoai', 'dien_thoai'],
        'email' => ['email', 'email_address', 'e_mail'],
        'province' => ['province', 'city', 'state', 'tinh', 'tinh_thanh', 'tinh_thanh_pho'],
        'high_school' => ['high_school', 'school', 'school_name', 'truong', 'truong_thpt'],
        'graduation_year' => ['graduation_year', 'nam_tot_nghiep'],
        'major' => ['major', 'nganh', 'nganh_quan_tam', 'nganh_hoc'],
        'gender' => ['gender', 'sex', 'gioi_tinh'],
        'dob' => ['dob', 'date_of_birth', 'birthday', 'ngay_sinh'],
        'note' => ['note', 'message', 'loi_nhan', 'ghi_chu'],
    ];

    /**
     * @param  array<string, mixed>  $fields  name => value (see flatten())
     * @param  array<string, string>|null  $custom  the integration mapping
     * @return array{data: array<string, string>, extra: array<string, string>}
     */
    public function map(array $fields, ?array $custom = null): array
    {
        $custom = collect($custom ?? [])
            ->mapWithKeys(fn ($key, $name) => [$this->normalize((string) $name) => (string) $key])
            ->filter(fn ($key) => in_array($key, self::KEYS, true))->all();
        $data = [];
        $extra = [];
        $first = null;
        $last = null;

        foreach ($fields as $name => $value) {
            $value = $this->text($value);

            if ($value === null) {
                continue;
            }

            $norm = $this->normalize((string) $name);
            $key = $custom[$norm] ?? $this->synonym($norm);

            if ($key !== null) {
                $data[$key] ??= $value;
            } elseif ($norm === 'first_name') {
                $first = $value;
            } elseif ($norm === 'last_name') {
                $last = $value;
            } else {
                $extra[(string) $name] = $value;
            }
        }

        if (! isset($data['full_name']) && ($first !== null || $last !== null)) {
            $data['full_name'] = trim(($last ?? '').' '.($first ?? ''));
        }

        return ['data' => $data, 'extra' => $extra];
    }

    /**
     * Facebook field_data [{name, values: [..]}], TikTok [{name|key, value}] or a plain {name: value} object, as name => value.
     *
     * @param  array<int|string, mixed>  $source
     * @return array<string, mixed>
     */
    public function flatten(array $source): array
    {
        if (! array_is_list($source)) {
            return $source;
        }

        $flat = [];

        foreach ($source as $item) {
            $name = is_array($item) ? ($item['name'] ?? $item['key'] ?? $item['field'] ?? null) : null;

            if ($name !== null) {
                $value = $item['values'] ?? $item['value'] ?? $item['answer'] ?? null;
                $flat[(string) $name] = is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return $flat;
    }

    private function synonym(string $norm): ?string
    {
        foreach (self::SYNONYMS as $key => $names) {
            if (in_array($norm, $names, true)) {
                return $key;
            }
        }

        return null;
    }

    private function normalize(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($name))), '_');
    }

    private function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : null;

        return $value === '' ? null : $value;
    }
}
