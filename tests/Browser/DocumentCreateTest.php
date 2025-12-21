<?php

use App\Models\Binder;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

it('creates a document from the browser form', function () {
    Prism::fake([
        new TextResponse(
            steps: collect([]),
            text: '{"type":"done"}',
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(1, 1),
            meta: new Meta('fake', 'fake-model'),
            messages: collect([]),
        ),
    ]);

    $user = User::factory()->create();
    $binder = Binder::factory()->create(['name' => 'Testowy segregator']);
    $category = Category::factory()->create(['name' => 'Testowa kategoria']);

    $this->actingAs($user);

    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
    $filePath = sys_get_temp_dir().'/scan-'.Str::random(8).'.png';
    file_put_contents($filePath, base64_decode($pngBase64));

    try {
        $page = visit('http://dokumenty.test/documents/create');

        $page->attach('#scans', $filePath)
            ->wait(1)
            ->type('#title', 'Faktura za prad')
            ->type('#reference_number', 'REF-2024-001')
            ->type('#issuer', 'Tauron')
            ->select('binder_id', (string) $binder->id)
            ->click($category->name)
            ->type('#tags', 'dom, prad, 2024')
            ->type('#document_date', '2024-04-29')
            ->type('#received_at', '2024-04-30')
            ->type('#notes', 'Testowe notatki')
            ->press('Zapisz')
            ->assertPathBeginsWith('/documents/');
    } finally {
        @unlink($filePath);
    }

    $document = Document::query()->first();

    expect($document)->not->toBeNull();

    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'binder_id' => $binder->id,
        'category_id' => $category->id,
        'title' => 'Faktura za prad',
        'reference_number' => 'REF-2024-001',
        'issuer' => 'Tauron',
        'document_date' => '2024-04-29',
        'received_at' => '2024-04-30',
        'notes' => 'Testowe notatki',
        'tags' => 'dom, prad, 2024',
    ]);
});
