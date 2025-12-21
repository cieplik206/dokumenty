<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('requires authentication for users management', function () {
    $response = $this->get(route('users.index'));

    $response->assertRedirect(route('login'));
});

it('forbids non-admin users from users management', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get(route('users.index'));

    $response->assertForbidden();
});

it('allows admins to create users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Alicja Kowalska',
        'email' => 'alicja@example.com',
        'password' => 'super-password',
        'password_confirmation' => 'super-password',
        'appearance' => 'dark',
        'is_admin' => 0,
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'alicja@example.com',
        'appearance' => 'dark',
        'is_admin' => false,
    ]);
});

it('allows admins to update users and change passwords', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['appearance' => 'system']);

    $response = $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'Nowe Imie',
        'email' => 'nowe@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'appearance' => 'light',
        'is_admin' => 1,
    ]);

    $response->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->name)->toBe('Nowe Imie')
        ->and($user->email)->toBe('nowe@example.com')
        ->and($user->appearance)->toBe('light')
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('new-password', $user->password))->toBeTrue();
});

it('prevents admins from removing their own admin role', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this
        ->actingAs($admin)
        ->from(route('users.edit', $admin))
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'appearance' => 'system',
            'is_admin' => 0,
        ]);

    $response
        ->assertSessionHasErrors('user')
        ->assertRedirect(route('users.edit', $admin));
});

it('prevents admins from deleting themselves', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this
        ->actingAs($admin)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $admin));

    $response
        ->assertSessionHasErrors('user')
        ->assertRedirect(route('users.index'));
});

it('allows admins to delete other users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

    $response->assertRedirect(route('users.index'));
    expect($user->fresh())->toBeNull();
});
