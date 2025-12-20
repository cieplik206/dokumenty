<?php

namespace Database\Factories;

use App\Models\Binder;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'binder_id' => Binder::factory(),
            'category_id' => Category::factory(),
            'title' => $this->faker->sentence(4),
            'reference_number' => $this->faker->optional()->bothify('REF-####-????'),
            'issuer' => $this->faker->optional()->company(),
            'document_date' => $this->faker->optional()->date(),
            'received_at' => $this->faker->optional()->date(),
            'notes' => $this->faker->optional()->paragraph(),
            'tags' => $this->faker->optional()->words(asText: true),
        ];
    }
}
