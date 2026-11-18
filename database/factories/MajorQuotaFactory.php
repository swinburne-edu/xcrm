<?php

namespace Database\Factories;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MajorQuota>
 */
class MajorQuotaFactory extends Factory
{
    protected $model = MajorQuota::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'major_id' => Major::factory(),
            'cycle_id' => AdmissionCycle::factory(),
            'quota_total' => 100,
            'quota_direct' => 10,
        ];
    }
}
