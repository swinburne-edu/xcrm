<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Models\Territory;

/**
 * Who looks after a province / high school: the territory's user (owner_id of a new case)
 * and/or team (team_id; a team without a user means the team's shared pool).
 */
final class TerritoryOwner
{
    public function __construct(
        public readonly Territory $territory,
        public readonly ?int $userId,
        public readonly ?int $teamId,
    ) {}
}
