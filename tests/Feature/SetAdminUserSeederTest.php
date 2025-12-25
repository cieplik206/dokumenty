<?php

use App\Models\User;
use Database\Seeders\SetAdminUserSeeder;

it('marks the configured user as admin', function () {
    $user = User::factory()->create([
        'email' => 'pawel@cieplinski.pl',
        'is_admin' => false,
    ]);

    (new SetAdminUserSeeder)->run();

    expect($user->refresh()->is_admin)->toBeTrue();
});
