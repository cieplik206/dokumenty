<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders document index variants', function (string $route, string $component) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route($route));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'grid' => ['documents.index.grid', 'documents/IndexGrid'],
    'table' => ['documents.index.table', 'documents/IndexTable'],
    'compact' => ['documents.index.compact', 'documents/IndexCompact'],
]);
