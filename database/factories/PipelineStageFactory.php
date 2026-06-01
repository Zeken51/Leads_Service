<?php

namespace Database\Factories;

use App\Domain\Pipeline\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'tenant_id'       => fake()->uuid(),
            'name'            => ucfirst($name),
            'slug'            => Str::slug($name, '_'),
            'order'           => fake()->numberBetween(1, 10),
            'color'           => '#3B82F6',
            'is_initial'      => false,
            'is_terminal'     => false,
            'maps_to_status'  => null,
        ];
    }

    public function initial(): static
    {
        return $this->state(['is_initial' => true, 'order' => 1]);
    }

    public function terminalWon(): static
    {
        return $this->state([
            'is_terminal'    => true,
            'maps_to_status' => 'won',
            'name'           => 'Ganado',
            'slug'           => 'won',
            'color'          => '#10B981',
        ]);
    }

    public function terminalLost(): static
    {
        return $this->state([
            'is_terminal'    => true,
            'maps_to_status' => 'lost',
            'name'           => 'Perdido',
            'slug'           => 'lost',
            'color'          => '#EF4444',
        ]);
    }
}
