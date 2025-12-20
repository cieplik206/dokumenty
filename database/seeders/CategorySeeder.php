<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labels = [
            'Faktury',
            'Ubezpieczenia',
            'Zdrowie',
            'Dom',
            'Podatki',
            'Bank',
            'Edukacja',
            'Samochod',
            'Media',
            'Urzedy',
        ];

        foreach ($labels as $label) {
            Category::query()->firstOrCreate([
                'name' => $label,
            ]);
        }
    }
}
