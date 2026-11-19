<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Platform\Models\CustomFieldDefinition;
use App\Domain\Shared\Services\PhoneNormalizer;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\Like;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Everything the screens need to know about the custom fields of one object (task 2.5): the definitions, the
 * validation rules for a `custom` input block, the normalized values to store in `custom_data`, the field list
 * with current values for the detail pages, and the list filters (`filter[cf_{key}]`).
 *
 * The definitions are scoped to the current organization by the model itself.
 */
class CustomFields
{
    public const FILTER_PREFIX = 'cf_';

    /** @return Collection<int, CustomFieldDefinition> */
    public function definitions(CustomFieldObject $object): Collection
    {
        return CustomFieldDefinition::query()->where('object', $object->value)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Validation rules for `{prefix}.{key}` of every field of the object.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(CustomFieldObject $object, string $prefix = 'custom'): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach ($this->definitions($object) as $definition) {
            $key = "{$prefix}.{$definition->key}";
            $options = array_values($definition->options ?? []);
            // Required only when the block is sent at all: a form that knows nothing about custom fields still saves.
            $required = $definition->is_required && $definition->type !== CustomFieldType::Boolean;

            $rules[$key] = [$required ? Rule::requiredIf(fn () => request()->has($prefix)) : 'nullable', ...match ($definition->type) {
                CustomFieldType::Text => ['string', 'max:255'],
                CustomFieldType::Textarea => ['string', 'max:5000'],
                CustomFieldType::Number => ['numeric', 'between:-999999999999,999999999999'],
                CustomFieldType::Date => ['date_format:Y-m-d'],
                CustomFieldType::Boolean => ['boolean'],
                CustomFieldType::Select => [Rule::in($options)],
                CustomFieldType::Multiselect => ['array', 'max:50'],
                CustomFieldType::Url => ['url', 'max:500'],
                CustomFieldType::Email => ['email', 'max:255'],
                CustomFieldType::Phone => ['string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! app(PhoneNormalizer::class)->isValid($value)) {
                        $fail('Số điện thoại không hợp lệ.');
                    }
                }],
            }];

            if ($definition->type === CustomFieldType::Multiselect) {
                $rules["{$key}.*"] = [Rule::in($options)];
            }
        }

        return $rules;
    }

    /** Friendly attribute names so errors read "Trường X …". @return array<string, string> */
    public function attributes(CustomFieldObject $object, string $prefix = 'custom'): array
    {
        return $this->definitions($object)->mapWithKeys(fn (CustomFieldDefinition $d) => ["{$prefix}.{$d->key}" => mb_strtolower($d->label)])->all();
    }

    /**
     * The values to store, by key. Only keys present in the input are returned; a blank value comes back as null
     * and means "remove this key". Unknown keys are dropped.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function clean(CustomFieldObject $object, array $input): array
    {
        $values = [];

        foreach ($this->definitions($object) as $definition) {
            if (! array_key_exists($definition->key, $input)) {
                continue;
            }

            $values[$definition->key] = $this->normalize($definition, $input[$definition->key]);
        }

        return $values;
    }

    /**
     * Field list with the current value of the record (null record = blank form), for detail pages and forms.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(CustomFieldObject $object, ?Model $record = null): array
    {
        $data = $record?->custom_data ?? [];

        return $this->definitions($object)->map(fn (CustomFieldDefinition $d) => [
            'key' => $d->key,
            'label' => $d->label,
            'type' => $d->type->value,
            'options' => array_values($d->options ?? []),
            'required' => $d->is_required,
            'value' => $data[$d->key] ?? null,
        ])->all();
    }

    /**
     * Detail-page block: the fields with their values plus where to save an inline edit.
     *
     * @return array{object: string, id: int, fields: list<array<string, mixed>>}
     */
    public function panel(CustomFieldObject $object, Model $record): array
    {
        return ['object' => $object->value, 'id' => (int) $record->getKey(), 'fields' => $this->fields($object, $record)];
    }

    /**
     * List filters for the filterable fields: filter[cf_{key}].
     *
     * @return list<Filter>
     */
    public function filters(CustomFieldObject $object): array
    {
        return $this->definitions($object)->filter(fn (CustomFieldDefinition $d) => $d->is_filterable)->map(function (CustomFieldDefinition $d): Filter {
            $name = self::FILTER_PREFIX.$d->key;
            $path = "custom_data->{$d->key}";

            return match ($d->type) {
                CustomFieldType::Number => Filter::callback($name, fn (Builder $q, int|float $v) => $q->where($q->qualifyColumn($path), $v), fn (mixed $v) => is_numeric($v) ? (str_contains((string) $v, '.') ? (float) $v : (int) $v) : null),
                CustomFieldType::Boolean => Filter::callback($name, fn (Builder $q, bool $v) => $q->where($q->qualifyColumn($path), $v), fn (mixed $v) => is_scalar($v) ? filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null),
                CustomFieldType::Select => Filter::callback($name, fn (Builder $q, string $v) => $q->where($q->qualifyColumn($path), $v), fn (mixed $v) => is_string($v) && $v !== '' ? $v : null),
                CustomFieldType::Multiselect => Filter::callback($name, fn (Builder $q, string $v) => $q->whereJsonContains($q->qualifyColumn($path), $v), fn (mixed $v) => is_string($v) && $v !== '' ? $v : null),
                CustomFieldType::Date => Filter::callback($name, function (Builder $q, array $range) use ($path): void {
                    isset($range['from']) && $q->where($q->qualifyColumn($path), '>=', $range['from']);
                    isset($range['to']) && $q->where($q->qualifyColumn($path), '<=', $range['to']);
                }, fn (mixed $v) => $this->dateRange($v)),
                default => Filter::callback($name, fn (Builder $q, string $v) => Like::anyColumn($q, [$path], $v), fn (mixed $v) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, 100) : null),
            };
        })->values()->all();
    }

    /**
     * FilterBar definitions for the same fields.
     *
     * @return list<array<string, mixed>>
     */
    public function filterDefinitions(CustomFieldObject $object): array
    {
        return $this->definitions($object)->filter(fn (CustomFieldDefinition $d) => $d->is_filterable)->map(function (CustomFieldDefinition $d): array {
            $base = ['key' => self::FILTER_PREFIX.$d->key, 'label' => $d->label];

            return match ($d->type) {
                CustomFieldType::Boolean => $base + ['type' => 'boolean', 'trueLabel' => 'Có', 'falseLabel' => 'Không'],
                CustomFieldType::Select, CustomFieldType::Multiselect => $base + ['type' => 'select', 'options' => array_map(fn (string $o) => ['value' => $o, 'label' => $o], array_values($d->options ?? []))],
                CustomFieldType::Date => $base + ['type' => 'date'],
                default => $base + ['type' => 'text', 'placeholder' => $d->type === CustomFieldType::Number ? 'Nhập số' : null],
            };
        })->values()->all();
    }

    private function normalize(CustomFieldDefinition $definition, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($definition->type) {
            CustomFieldType::Number => is_numeric($value) ? (str_contains((string) $value, '.') ? (float) $value : (int) $value) : null,
            CustomFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            CustomFieldType::Multiselect => array_values(array_unique(array_map('strval', (array) $value))),
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }

    /** @return array{from?: string, to?: string}|null */
    private function dateRange(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $range = [];

        foreach (['from', 'to'] as $edge) {
            $v = $value[$edge] ?? null;

            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                continue;
            }

            try {
                CarbonImmutable::createFromFormat('!Y-m-d', $v)->format('Y-m-d') === $v && ($range[$edge] = $v);
            } catch (Throwable) {
            }
        }

        return $range === [] ? null : $range;
    }
}
