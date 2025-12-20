<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Binder>
 */
class BinderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Segregator '.$this->faker->unique()->numberBetween(1, 20),
            'location' => $this->faker->optional()->randomElement([
                'Szafka 1',
                'Szafka 2',
                'Biurko',
                'Garderoba',
            ]),
            'description' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(1, 20),
        ];
    }
}
