<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SetAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()
            ->where('email', 'pawel@cieplinski.pl')
            ->first();

        if (! $user) {
            return;
        }

        $user->update([
            'is_admin' => true,
        ]);
    }
}
