<?php

namespace App\Domain\Admission\Services;

/**
 * Outcome of ScoreCalculator: the three score parts, the total and a breakdown to show the user.
 * raw / final are null when the applicant lacks the scores the formula needs (see $missing).
 */
final class ScoreResult
{
    /**
     * @param  array<string, mixed>  $breakdown
     * @param  list<string>  $missing  what is needed but absent: a subject code, "combination", "formula", "score"…
     */
    public function __construct(
        public readonly ?float $raw,
        public readonly float $priorityBonus,
        public readonly float $encourageBonus,
        public readonly ?float $final,
        public readonly array $breakdown = [],
        public readonly array $missing = [],
    ) {}

    public function complete(): bool
    {
        return $this->final !== null;
    }

    /** @return array<string, mixed> columns of the applications table */
    public function toAttributes(): array
    {
        return [
            'raw_score' => $this->raw,
            'priority_bonus' => $this->raw === null ? null : $this->priorityBonus,
            'encourage_bonus' => $this->raw === null ? null : $this->encourageBonus,
            'final_score' => $this->final,
            'score_breakdown' => $this->breakdown + ['missing' => $this->missing],
        ];
    }
}
