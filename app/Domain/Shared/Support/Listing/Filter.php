<?php

namespace App\Domain\Shared\Support\Listing;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * An allowed list filter: `?filter[{name}]=...` (see ListQuery).
 */
final class Filter
{
    /**
     * @param  Closure(Builder, mixed): void  $apply
     */
    private function __construct(
        public readonly string $name,
        private readonly Closure $apply,
        private readonly Closure $normalize,
    ) {}

    /** column = value; "a,b" or an array becomes whereIn. */
    public static function exact(string $name, ?string $column = null): self
    {
        $column ??= $name;

        return new self(
            $name,
            function (Builder $query, array $values) use ($column): void {
                count($values) === 1
                    ? $query->where($query->qualifyColumn($column), $values[0])
                    : $query->whereIn($query->qualifyColumn($column), $values);
            },
            fn (mixed $value): ?array => self::listValue($value),
        );
    }

    /** column LIKE %value% */
    public static function partial(string $name, ?string $column = null): self
    {
        return self::search($name, [$column ?? $name]);
    }

    /**
     * Free-text search across several columns (OR), e.g. filter[q].
     *
     * @param  list<string>  $columns
     */
    public static function search(string $name, array $columns): self
    {
        return new self(
            $name,
            fn (Builder $query, string $term) => Like::anyColumn($query, $columns, $term),
            function (mixed $value): ?string {
                $term = is_string($value) ? trim($value) : '';

                return $term === '' ? null : mb_substr($term, 0, 100);
            },
        );
    }

    /** "1"/"true" or "0"/"false" */
    public static function boolean(string $name, ?string $column = null): self
    {
        $column ??= $name;

        return new self(
            $name,
            fn (Builder $query, bool $value) => $query->where($query->qualifyColumn($column), $value),
            function (mixed $value): ?bool {
                return is_string($value) || is_bool($value) || is_int($value)
                    ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : null;
            },
        );
    }

    /**
     * filter[{name}][from]=YYYY-MM-DD & filter[{name}][to]=YYYY-MM-DD, both inclusive.
     * Dates are calendar days in the display timezone; timestamp columns are compared in UTC.
     */
    public static function dateRange(string $name, ?string $column = null, bool $isDateColumn = false): self
    {
        $column ??= $name;

        return new self(
            $name,
            function (Builder $query, array $range) use ($column, $isDateColumn): void {
                $qualified = $query->qualifyColumn($column);
                $tz = config('app.display_timezone');

                if (isset($range['from'])) {
                    $from = CarbonImmutable::createFromFormat('!Y-m-d', $range['from'], $tz);
                    $query->where($qualified, '>=', $isDateColumn
                        ? $from->toDateString()
                        : $from->startOfDay()->utc()->toDateTimeString());
                }

                if (isset($range['to'])) {
                    $to = CarbonImmutable::createFromFormat('!Y-m-d', $range['to'], $tz);
                    $query->where($qualified, '<=', $isDateColumn
                        ? $to->toDateString()
                        : $to->endOfDay()->utc()->toDateTimeString());
                }
            },
            function (mixed $value): ?array {
                if (! is_array($value)) {
                    return null;
                }

                $range = array_filter([
                    'from' => self::dateValue($value['from'] ?? null),
                    'to' => self::dateValue($value['to'] ?? null),
                ]);

                return $range === [] ? null : $range;
            },
        );
    }

    /**
     * Custom filter. The value is passed as given (string or array); return null from $normalize to ignore it.
     *
     * @param  Closure(Builder, mixed): void  $apply
     * @param  (Closure(mixed): mixed)|null  $normalize
     */
    public static function callback(string $name, Closure $apply, ?Closure $normalize = null): self
    {
        return new self(
            $name,
            $apply,
            $normalize ?? fn (mixed $value): mixed => ($value === '' || $value === [] ? null : $value),
        );
    }

    /**
     * Normalized value, or null when the filter should not apply.
     */
    public function normalize(mixed $value): mixed
    {
        return ($this->normalize)($value);
    }

    public function apply(Builder $query, mixed $normalizedValue): void
    {
        ($this->apply)($query, $normalizedValue);
    }

    /**
     * @return list<string>|null
     */
    private static function listValue(mixed $value): ?array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);
        $values = array_values(array_filter(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $values),
            fn (string $v) => $v !== '',
        ));

        return $values === [] ? null : array_slice($values, 0, 100);
    }

    private static function dateValue(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
