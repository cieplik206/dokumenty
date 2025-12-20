<?php

use App\Models\Binder;
use App\Models\User;

test('binders index requires authentication', function () {
    $response = $this->get(route('binders.index'));

    $response->assertRedirect(route('login'));
});

test('user can create a binder', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('binders.store'), [
        'name' => 'Segregator Testowy',
        'location' => 'Szafka 1',
        'description' => 'Dokumenty domowe',
        'sort_order' => 1,
    ]);

    $binder = Binder::query()->first();

    expect($binder)->not->toBeNull();
    expect($binder?->name)->toBe('Segregator Testowy');

    $response->assertRedirect(route('binders.show', $binder));
});

test('user can update a binder', function () {
    $user = User::factory()->create();
    $binder = Binder::factory()->create([
        'name' => 'Stary segregator',
    ]);

    $response = $this->actingAs($user)->put(route('binders.update', $binder), [
        'name' => 'Nowy segregator',
        'location' => 'Szafka 2',
        'description' => 'Opis',
        'sort_order' => 2,
    ]);

    $response->assertRedirect(route('binders.show', $binder));

    expect($binder->refresh()->name)->toBe('Nowy segregator');
});

test('user can delete a binder', function () {
    $user = User::factory()->create();
    $binder = Binder::factory()->create();

    $response = $this->actingAs($user)->delete(route('binders.destroy', $binder));

    $response->assertRedirect(route('binders.index'));
    $this->assertDatabaseMissing('binders', [
        'id' => $binder->id,
    ]);
});
