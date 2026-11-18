<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\MajorQuota;
use Illuminate\Support\Facades\DB;

/**
 * Flow F1 step 1: start a new season from a previous one. Copies, into a new cycle in "preparing"
 * (never current):
 *   - rounds, with their dates moved by the difference in years;
 *   - cycle methods (formula, conditions, documents), so rule R1 still holds;
 *   - major quotas (the old quota is kept as a suggestion, previous cutoffs untouched);
 *   - major ↔ combination and major ↔ method links, pointing at the new cycle's rows.
 * The source cycle is only read, so a closed cycle can be cloned too.
 */
class CloneCycle
{
    /**
     * @param  array{name: string, year: int}  $data  validated by CloneCycleRequest
     * @return array{cycle: AdmissionCycle, counts: array{rounds: int, methods: int, quotas: int, combinations: int, major_methods: int}}
     */
    public function handle(AdmissionCycle $source, array $data): array
    {
        return DB::transaction(function () use ($source, $data): array {
            $shift = (int) $data['year'] - $source->year;

            $cycle = new AdmissionCycle([
                'name' => $data['name'],
                'year' => $data['year'],
                'level' => $source->level,
                'status' => CycleStatus::Preparing,
                'is_current' => false,
                'start_date' => $source->start_date?->copy()->addYearsNoOverflow($shift),
                'end_date' => $source->end_date?->copy()->addYearsNoOverflow($shift),
                'target_enrolled' => $source->target_enrolled,
                'regulation_ref' => $source->regulation_ref,
            ]);
            $cycle->save();

            $counts = ['rounds' => 0, 'methods' => 0, 'quotas' => 0, 'combinations' => 0, 'major_methods' => 0];

            foreach ($source->rounds as $round) {
                /** @var AdmissionRound $round */
                AdmissionRound::query()->create([
                    'cycle_id' => $cycle->id,
                    'name' => $round->name,
                    'type' => $round->type,
                    'apply_start' => $round->apply_start?->copy()->addYearsNoOverflow($shift),
                    'apply_end' => $round->apply_end?->copy()->addYearsNoOverflow($shift),
                    'result_date' => $round->result_date?->copy()->addYearsNoOverflow($shift),
                    'confirm_deadline' => $round->confirm_deadline?->copy()->addYearsNoOverflow($shift),
                    'sort_order' => $round->sort_order,
                ]);
                $counts['rounds']++;
            }

            /** @var array<int, int> $methodMap old cycle_method id => new id */
            $methodMap = [];

            foreach ($source->methods as $method) {
                /** @var CycleMethod $method */
                $copy = $method->replicate(['cycle_id']);
                $copy->cycle_id = $cycle->id;
                $copy->save();
                $methodMap[$method->id] = $copy->id;
                $counts['methods']++;
            }

            foreach (MajorQuota::query()->where('cycle_id', $source->id)->get() as $quota) {
                $copy = $quota->replicate(['cycle_id']);
                $copy->cycle_id = $cycle->id;
                $copy->save();
                $counts['quotas']++;
            }

            $combinations = DB::table('major_combinations')->where('cycle_id', $source->id)->get(['major_id', 'combination_id'])
                ->map(fn ($row) => ['major_id' => $row->major_id, 'combination_id' => $row->combination_id, 'cycle_id' => $cycle->id])->all();
            DB::table('major_combinations')->insert($combinations);
            $counts['combinations'] = count($combinations);

            $majorMethods = DB::table('major_cycle_methods')->whereIn('cycle_method_id', array_keys($methodMap))->get(['major_id', 'cycle_method_id'])
                ->map(fn ($row) => ['major_id' => $row->major_id, 'cycle_method_id' => $methodMap[$row->cycle_method_id]])->all();
            DB::table('major_cycle_methods')->insert($majorMethods);
            $counts['major_methods'] = count($majorMethods);

            return ['cycle' => $cycle->refresh(), 'counts' => $counts];
        });
    }
}
