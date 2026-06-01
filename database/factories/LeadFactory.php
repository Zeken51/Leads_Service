<?php

namespace Database\Factories;

use App\Domain\Leads\Enums\LeadPriority;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => fake()->uuid(),
            'source_system'  => 'web_form',
            'source_channel' => 'landing_page',
            'status'         => LeadStatus::Active,
            'priority'       => LeadPriority::Medium,
            'customer_name'  => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
        ];
    }

    public function won(): static
    {
        return $this->state(['status' => LeadStatus::Won, 'won_at' => now()]);
    }

    public function lost(string $reason = 'No budget'): static
    {
        return $this->state([
            'status'      => LeadStatus::Lost,
            'lost_at'     => now(),
            'lost_reason' => $reason,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(['followup_at' => now()->subDay()]);
    }
}
