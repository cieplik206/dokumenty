<?php

namespace Database\Factories;

use App\Models\DocumentIntake;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentIntake>
 */
class DocumentIntakeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => DocumentIntake::STATUS_UPLOADED,
            'document_id' => null,
            'original_name' => $this->faker->word().'.pdf',
            'storage_type' => null,
            'fields' => null,
            'extracted_text' => null,
            'extracted_content' => null,
            'ai_metadata' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
            'finalized_at' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => DocumentIntake::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => DocumentIntake::STATUS_DONE,
            'fields' => [
                'title' => $this->faker->sentence(3),
                'notes' => $this->faker->sentence(),
                'tags' => [$this->faker->word(), $this->faker->word()],
            ],
            'extracted_text' => $this->faker->paragraph(),
            'extracted_content' => [
                'summary' => $this->faker->sentence(),
            ],
            'ai_metadata' => [
                'confidence' => $this->faker->randomFloat(2, 0.4, 0.95),
                'language' => 'pl',
            ],
            'started_at' => now()->subMinutes(1),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => DocumentIntake::STATUS_FAILED,
            'error_message' => 'Nie udalo sie przeanalizowac skanu.',
            'started_at' => now()->subMinutes(1),
            'finished_at' => now(),
        ]);
    }
}
