<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Shared\Support\DisplayTime;

/**
 * Create or update a round of a cycle. Times arrive in Vietnam time and are stored in UTC.
 */
class SaveRound
{
    /**
     * @param  array<string, mixed>  $data  validated by RoundRequest
     */
    public function handle(AdmissionCycle $cycle, ?AdmissionRound $round, array $data): AdmissionRound
    {
        $round ??= new AdmissionRound(['cycle_id' => $cycle->id]);

        $round->fill([
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'apply_start' => DisplayTime::toUtc($data['apply_start'] ?? null),
            'apply_end' => DisplayTime::toUtc($data['apply_end'] ?? null),
            'result_date' => $data['result_date'] ?? null,
            'confirm_deadline' => DisplayTime::toUtc($data['confirm_deadline'] ?? null),
            'sort_order' => $data['sort_order'] ?? ($round->exists ? $round->sort_order : ((int) $cycle->rounds()->max('sort_order') + 10)),
        ])->save();

        return $round;
    }
}
