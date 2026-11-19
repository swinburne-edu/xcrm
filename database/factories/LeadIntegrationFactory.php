<?php

namespace Database\Factories;

use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadIntegration>
 */
class LeadIntegrationFactory extends Factory
{
    protected $model = LeadIntegration::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider' => LeadProvider::Facebook,
            'name' => 'Lead Ads '.fake()->unique()->numberBetween(1, 99999),
            'secret' => Str::random(32),
            'verify_token' => Str::random(24),
            'access_token' => 'page-token',
            'is_active' => true,
        ];
    }

    public function tiktok(): static
    {
        return $this->state(fn () => ['provider' => LeadProvider::TikTok, 'verify_token' => null, 'access_token' => null]);
    }
}
