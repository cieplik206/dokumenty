<?php

namespace Database\Seeders;

use App\Models\Binder;
use Illuminate\Database\Seeder;

class BinderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labels = collect(range(1, 10))
            ->map(fn (int $number) => 'Segregator '.$number);

        $labels->each(function (string $label, int $index): void {
            Binder::factory()->create([
                'name' => $label,
                'sort_order' => $index + 1,
            ]);
        });
    }
}
