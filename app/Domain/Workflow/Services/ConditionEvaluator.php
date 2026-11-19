<?php

namespace App\Domain\Workflow\Services;

use Illuminate\Database\Eloquent\Model;

/** All conditions must hold (AND). An empty list always holds. */
class ConditionEvaluator
{
    /** @param  list<array{field: string, operator: string, value?: mixed}>|null  $conditions */
    public function passes(?array $conditions, Model $subject): bool
    {
        foreach ($conditions ?? [] as $c) {
            if (! $this->check($subject->getAttribute($c['field']), $c['operator'], $c['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function check(mixed $actual, string $operator, mixed $expected): bool
    {
        $actual = $actual instanceof \BackedEnum ? $actual->value : $actual;
        $blank = $actual === null || $actual === '';

        return match ($operator) {
            'empty' => $blank,
            'not_empty' => ! $blank,
            'eq' => ! $blank && $this->same($actual, $expected),
            'neq' => $blank || ! $this->same($actual, $expected),
            'gt' => ! $blank && is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'lt' => ! $blank && is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            default => false,
        };
    }

    private function same(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            return filter_var($actual, FILTER_VALIDATE_BOOLEAN) === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $actual === (string) $expected;
    }
}
