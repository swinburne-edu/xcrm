<?php

namespace App\Domain\Platform\Services;

use App\Domain\Organization\Models\Organization;

/**
 * The assignment-related switches of an organization, kept in organizations.settings.assignment (task 2.4):
 *   sla_alerts          true (default): tell the team leader when a case sits past its stage SLA without any contact
 *   recall_after_days   null (default, off) or N: a case with no activity for N days goes back to the shared pool
 */
class AssignmentSettings
{
    public const MAX_RECALL_DAYS = 365;

    public function slaAlerts(Organization $organization): bool
    {
        return (bool) ($organization->settings['assignment']['sla_alerts'] ?? true);
    }

    public function recallAfterDays(Organization $organization): ?int
    {
        $days = $organization->settings['assignment']['recall_after_days'] ?? null;

        return is_numeric($days) && (int) $days >= 1 ? min((int) $days, self::MAX_RECALL_DAYS) : null;
    }

    /** @return array{sla_alerts: bool, recall_after_days: int|null} */
    public function all(Organization $organization): array
    {
        return ['sla_alerts' => $this->slaAlerts($organization), 'recall_after_days' => $this->recallAfterDays($organization)];
    }

    /** @param  array{sla_alerts?: bool|null, recall_after_days?: int|string|null}  $values */
    public function save(Organization $organization, array $values): void
    {
        $settings = $organization->settings ?? [];
        $days = $values['recall_after_days'] ?? null;
        $settings['assignment'] = [
            'sla_alerts' => (bool) ($values['sla_alerts'] ?? true),
            'recall_after_days' => is_numeric($days) && (int) $days >= 1 ? min((int) $days, self::MAX_RECALL_DAYS) : null,
        ];

        $organization->forceFill(['settings' => $settings])->save();
    }
}
