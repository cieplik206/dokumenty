<?php

use App\Models\Binder;
use App\Models\Document;
use App\Models\DocumentIntake;
use App\Models\User;
use Illuminate\Support\Str;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

it('creates a document from intake uploads', function () {
    $ndjson = implode("\n", [
        json_encode([
            'type' => 'field',
            'key' => 'title',
            'value' => 'Faktura za prad',
        ], JSON_UNESCAPED_UNICODE),
        json_encode(['type' => 'done'], JSON_UNESCAPED_UNICODE),
    ]);

    Prism::fake([
        new TextResponse(
            steps: collect([]),
            text: $ndjson,
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(1, 1),
            meta: new Meta('fake', 'fake-model'),
            messages: collect([]),
        ),
    ]);

    $user = User::factory()->create();
    Binder::factory()->create(['name' => 'Testowy segregator']);

    $this->actingAs($user);

    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
    $filePath = sys_get_temp_dir().'/scan-'.Str::random(8).'.png';
    file_put_contents($filePath, base64_decode($pngBase64));

    try {
        $page = visit('http://dokumenty.test/documents/create');

        $page->attach('#scans', $filePath)
            ->wait(2)
            ->assertSee('Czeka na start')
            ->press('Analizuj')
            ->wait(2)
            ->assertSee('Gotowe do decyzji')
            ->press('Elektroniczna')
            ->wait(1)
            ->assertSee('Decyzja zapisana.');
    } finally {
        @unlink($filePath);
    }

    $document = Document::query()->first();

    $intake = DocumentIntake::query()->first();

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('Faktura za prad')
        ->and($document->status)->toBe(Document::STATUS_READY)
        ->and($document->binder_id)->toBeNull();

    expect($intake)->not->toBeNull()
        ->and($intake->status)->toBe(DocumentIntake::STATUS_FINALIZED)
        ->and($intake->storage_type)->toBe('electronic')
        ->and($intake->document_id)->toBe($document?->id);
});
