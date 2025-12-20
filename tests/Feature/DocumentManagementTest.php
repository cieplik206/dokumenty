<?php

use App\Models\Binder;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('documents index requires authentication', function () {
    $response = $this->get(route('documents.index'));

    $response->assertRedirect(route('login'));
});

test('user can create a document with scans', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $binder = Binder::factory()->create();
    $category = Category::factory()->create();
    $scan = UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf');

    $response = $this->actingAs($user)->post(route('documents.store'), [
        'binder_id' => $binder->id,
        'category_id' => $category->id,
        'title' => 'Faktura za prad',
        'reference_number' => 'FV/2024/10',
        'issuer' => 'Energia',
        'document_date' => now()->toDateString(),
        'received_at' => now()->toDateString(),
        'notes' => 'Platnosc do 10-tego.',
        'tags' => 'dom,prad,2024',
        'scans' => [$scan],
    ]);

    $document = Document::query()->first();

    expect($document)->not->toBeNull();
    expect($document->getMedia('scans'))->toHaveCount(1);

    $response->assertRedirect(route('documents.show', $document));
});

test('documents can be filtered by query', function () {
    $user = User::factory()->create();
    $binder = Binder::factory()->create();

    Document::factory()->for($binder)->create([
        'title' => 'Umowa najmu',
    ]);

    Document::factory()->for($binder)->create([
        'title' => 'Faktura gaz',
    ]);

    $response = $this->actingAs($user)->get(route('documents.index', [
        'q' => 'Umowa',
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('documents/Index')
        ->has('documents.data', 1)
        ->where('documents.data.0.title', 'Umowa najmu'));
});
