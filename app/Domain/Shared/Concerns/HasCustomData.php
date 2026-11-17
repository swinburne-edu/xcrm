<?php

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Twenty-style custom fields stored in the JSON column `custom_data`.
 * Field definitions live in custom_field_definitions (task 2.5).
 *
 * @mixin Model
 */
trait HasCustomData
{
    public function initializeHasCustomData(): void
    {
        $this->mergeCasts(['custom_data' => 'array']);
    }

    public function getCustom(string $key, mixed $default = null): mixed
    {
        return ($this->custom_data ?? [])[static::assertCustomKey($key)] ?? $default;
    }

    public function setCustom(string $key, mixed $value): static
    {
        $data = $this->custom_data ?? [];
        $data[static::assertCustomKey($key)] = $value;
        $this->custom_data = $data;

        return $this;
    }

    public function forgetCustom(string $key): static
    {
        $data = $this->custom_data ?? [];
        unset($data[static::assertCustomKey($key)]);
        $this->custom_data = $data;

        return $this;
    }

    /**
     * where custom_data->key {operator} value
     */
    public function scopeWhereCustom(Builder $query, string $key, mixed $operator, mixed $value = null): Builder
    {
        $column = 'custom_data->'.static::assertCustomKey($key);

        return func_num_args() === 3
            ? $query->where($column, $operator)
            : $query->where($column, $operator, $value);
    }

    /**
     * Keys are snake_case (same rule as custom_field_definitions.key);
     * this also keeps user input out of the JSON path expression.
     */
    protected static function assertCustomKey(string $key): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
            throw new InvalidArgumentException("Invalid custom field key [{$key}].");
        }

        return $key;
    }
}
