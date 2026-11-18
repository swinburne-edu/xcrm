<?php

namespace Database\Factories;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 999999);

        return [
            'organization_id' => Organization::factory(),
            'cycle_id' => AdmissionCycle::factory(),
            'code' => "camp-{$n}",
            'name' => "Chiến dịch {$n}",
            'status' => CampaignStatus::Draft,
        ];
    }

    public function status(CampaignStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
