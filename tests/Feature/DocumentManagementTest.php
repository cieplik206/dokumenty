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
        'is_paper' => 1,
        'binder_id' => $binder->id,
        'category_id' => $category->id,
        'title' => 'Faktura za prad',
        'reference_number' => 'FV/2024/10',
        'issuer' => 'Energia',
        'document_date' => now()->toDateString(),
        'received_at' => now()->toDateString(),
        'notes' => 'Platnosc do 10-tego.',
        'tags' => 'dom,prad,2024',
        'extracted_content' => json_encode([
            'summary' => 'Podsumowanie dokumentu.',
            'key_points' => ['Kwota 120.00 PLN'],
            'search_text' => 'Podsumowanie dokumentu. Kwota 120.00 PLN',
        ]),
        'ai_metadata' => json_encode([
            'confidence' => 0.82,
            'language' => 'pl',
        ]),
        'scans' => [$scan],
    ]);

    $document = Document::query()->first();

    expect($document)->not->toBeNull();
    expect($document->getMedia('scans'))->toHaveCount(1);
    expect($document->extracted_content)->toMatchArray([
        'summary' => 'Podsumowanie dokumentu.',
    ]);
    expect($document->ai_metadata)->toMatchArray([
        'language' => 'pl',
    ]);

    $response->assertRedirect(route('documents.show', $document));
});

test('user can create an electronic document without binder', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post(route('documents.store'), [
        'is_paper' => 0,
        'category_id' => $category->id,
        'title' => 'Polisa online',
    ]);

    $document = Document::query()->first();

    expect($document)->not->toBeNull();
    expect($document->binder_id)->toBeNull();

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('documents.show', $document));
});

test('paper document requires binder', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post(route('documents.store'), [
        'is_paper' => 1,
        'category_id' => $category->id,
        'title' => 'Potwierdzenie przelewu',
    ]);

    $response->assertSessionHasErrors(['binder_id']);
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
