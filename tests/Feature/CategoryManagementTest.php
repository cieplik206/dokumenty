<?php

use App\Models\Binder;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;

it('requires authentication for categories', function () {
    $response = $this->get(route('categories.index'));

    $response->assertRedirect(route('login'));
});

it('allows users to create categories', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Faktury',
        'description' => 'Rachunki i faktury domowe.',
    ]);

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('categories', [
        'name' => 'Faktury',
    ]);
});

it('allows users to update categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create([
        'name' => 'Podatki',
    ]);

    $response = $this->actingAs($user)->put(route('categories.update', $category), [
        'name' => 'Podatki i urzedy',
        'description' => 'Deklaracje i korespondencja urzedowa.',
    ]);

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Podatki i urzedy',
    ]);
});

it('prevents deleting categories with documents', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $binder = Binder::factory()->create();

    Document::factory()->for($binder)->create([
        'category_id' => $category->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('categories.index'))
        ->delete(route('categories.destroy', $category));

    $response
        ->assertSessionHasErrors('category')
        ->assertRedirect(route('categories.index'));

    expect($category->fresh())->not->toBeNull();
});

it('allows deleting empty categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

    $response->assertRedirect(route('categories.index'));
    expect($category->fresh())->toBeNull();
});
