<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\AssignmentStrategy;
use App\Domain\Platform\Models\AssignmentRule;

/**
 * Creates or edits an assignment rule. Only the known condition keys are kept, as clean lists (ids as integers,
 * school tiers as A / B / C); a territory rule carries no people or team, the other strategies carry their candidates.
 */
class SaveAssignmentRule
{
    /**
     * @param  array{name: string, priority?: int|string|null, strategy: string, is_active?: bool|null, user_ids?: list<int|string>|null, team_id?: int|string|null, conditions?: array<string, mixed>|null}  $data  validated by AssignmentRuleRequest
     */
    public function handle(?AssignmentRule $rule, array $data): AssignmentRule
    {
        $strategy = AssignmentStrategy::from($data['strategy']);
        $people = $strategy->needsPeople();

        $rule ??= new AssignmentRule;
        $rule->fill([
            'name' => trim($data['name']),
            'priority' => filled($data['priority'] ?? null) ? (int) $data['priority'] : 100,
            'strategy' => $strategy,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'user_ids' => $people ? $this->ids($data['user_ids'] ?? []) : [],
            'team_id' => $people && filled($data['team_id'] ?? null) ? (int) $data['team_id'] : null,
            'conditions' => $this->conditions($data['conditions'] ?? []),
        ])->save();

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<int|string>>|null
     */
    private function conditions(array $input): ?array
    {
        $clean = [];

        foreach (AssignmentRule::CONDITION_KEYS as $key) {
            $values = (array) ($input[$key] ?? []);
            $list = $key === 'school_tiers'
                ? array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper(trim((string) $v)), $values), fn (string $v) => in_array($v, ['A', 'B', 'C'], true))))
                : $this->ids($values);

            if ($list !== []) {
                $clean[$key] = $list;
            }
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        return array_values(array_unique(array_map('intval', array_filter($values, fn ($v) => is_numeric($v) && (int) $v > 0))));
    }
}
