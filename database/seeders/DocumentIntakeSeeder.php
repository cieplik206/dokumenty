<?php

namespace Database\Seeders;

use App\Models\DocumentIntake;
use Illuminate\Database\Seeder;

class DocumentIntakeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DocumentIntake::factory()->count(2)->create();
        DocumentIntake::factory()->done()->create();
    }
}
