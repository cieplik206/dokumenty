<?php

namespace Database\Seeders;

use App\Models\Binder;
use App\Models\Category;
use App\Models\Document;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::query()->get();

        Binder::query()
            ->each(function (Binder $binder) use ($categories): void {
                Document::factory()
                    ->count(5)
                    ->state(function () use ($categories): array {
                        return [
                            'category_id' => $categories->random()->id ?? null,
                        ];
                    })
                    ->for($binder)
                    ->create();
            });
    }
}
