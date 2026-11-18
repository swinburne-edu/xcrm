<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a team with its leader and members. The leader is always a member.
 */
class SaveTeam
{
    /**
     * @param  array<string, mixed>  $data  validated by TeamRequest
     */
    public function handle(?Team $team, array $data): Team
    {
        return DB::transaction(function () use ($team, $data): Team {
            $team ??= new Team;

            $team->fill([
                'name' => $data['name'],
                'leader_id' => $data['leader_id'] ?? null,
            ])->save();

            $members = collect($data['member_ids'] ?? []);

            if (filled($data['leader_id'] ?? null)) {
                $members->push($data['leader_id']);
            }

            $team->members()->sync($members->map(fn ($id) => (int) $id)->unique()->values()->all());

            return $team;
        });
    }
}
