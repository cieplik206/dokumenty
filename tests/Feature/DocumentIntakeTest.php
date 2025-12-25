<?php

use App\Actions\Documents\AnalyzeDocumentIntake;
use App\Jobs\ProcessDocumentIntake;
use App\Models\Binder;
use App\Models\Document;
use App\Models\DocumentIntake;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

test('document intake creates an upload record for each file', function () {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create();
    $scan = UploadedFile::fake()->image('scan.png', 10, 10);
    $scanTwo = UploadedFile::fake()->image('scan-2.png', 10, 10);

    $response = $this
        ->actingAs($user)
        ->post(route('documents.intake'), [
            'scans' => [$scan, $scanTwo],
        ]);

    $response
        ->assertAccepted()
        ->assertJsonCount(2, 'items');

    $intakes = DocumentIntake::query()->get();

    expect($intakes)->toHaveCount(2)
        ->and($intakes->first()->status)->toBe(DocumentIntake::STATUS_UPLOADED);

    Queue::assertNothingPushed();
});

test('document intake index returns bulk payload', function () {
    $intake = DocumentIntake::factory()->done()->create();
    $failed = DocumentIntake::factory()->failed()->create(['user_id' => $intake->user_id]);

    $response = $this
        ->actingAs($intake->user)
        ->getJson(route('documents.intake.index', [
            'ids' => implode(',', [$intake->id, $failed->id]),
        ]));

    $response
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonFragment(['id' => $intake->id, 'status' => DocumentIntake::STATUS_DONE])
        ->assertJsonFragment(['id' => $failed->id, 'status' => DocumentIntake::STATUS_FAILED]);
});

test('document intake can be started manually', function () {
    Queue::fake();

    $intake = DocumentIntake::factory()->create([
        'status' => DocumentIntake::STATUS_UPLOADED,
    ]);

    $response = $this
        ->actingAs($intake->user)
        ->postJson(route('documents.intake.start', $intake));

    $response
        ->assertOk()
        ->assertJsonFragment([
            'id' => $intake->id,
            'status' => DocumentIntake::STATUS_QUEUED,
        ]);

    $intake->refresh();

    expect($intake->status)->toBe(DocumentIntake::STATUS_QUEUED);

    Queue::assertPushed(ProcessDocumentIntake::class);
});

test('document intake can be finalized as paper', function () {
    $user = User::factory()->create();
    $binder = Binder::factory()->create();

    $document = Document::factory()->create([
        'status' => Document::STATUS_DRAFT,
        'binder_id' => null,
    ]);

    $intake = DocumentIntake::factory()->done()->create([
        'user_id' => $user->id,
        'document_id' => $document->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('documents.intake.finalize', $intake), [
            'storage_type' => 'paper',
            'binder_id' => $binder->id,
        ]);

    $response->assertOk();

    $document->refresh();

    expect($document->status)->toBe(Document::STATUS_READY)
        ->and($document->binder_id)->toBe($binder->id);
});

test('finalized intake can be removed without deleting document', function () {
    $user = User::factory()->create();
    $document = Document::factory()->create([
        'status' => Document::STATUS_READY,
    ]);

    $intake = DocumentIntake::factory()->create([
        'user_id' => $user->id,
        'document_id' => $document->id,
        'status' => DocumentIntake::STATUS_FINALIZED,
        'storage_type' => 'paper',
        'finalized_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('documents.intake.destroy', $intake));

    $response->assertNoContent();

    expect(DocumentIntake::query()->whereKey($intake->id)->exists())->toBeFalse()
        ->and(Document::query()->whereKey($document->id)->exists())->toBeTrue();
});

test('document intake bulk destroy removes selected intakes', function () {
    $user = User::factory()->create();
    $document = Document::factory()->create([
        'status' => Document::STATUS_READY,
    ]);

    $finalized = DocumentIntake::factory()->create([
        'user_id' => $user->id,
        'document_id' => $document->id,
        'status' => DocumentIntake::STATUS_FINALIZED,
        'storage_type' => 'paper',
        'finalized_at' => now(),
    ]);

    $uploaded = DocumentIntake::factory()->create([
        'user_id' => $user->id,
        'status' => DocumentIntake::STATUS_UPLOADED,
    ]);

    $other = DocumentIntake::factory()->create();

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('documents.intake.destroy.bulk'), [
            'ids' => [$finalized->id, $uploaded->id, $other->id],
        ]);

    $response->assertNoContent();

    expect(DocumentIntake::query()->whereKey($finalized->id)->exists())->toBeFalse()
        ->and(DocumentIntake::query()->whereKey($uploaded->id)->exists())->toBeFalse()
        ->and(DocumentIntake::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(Document::query()->whereKey($document->id)->exists())->toBeTrue();
});

test('document intake can be retried when done without document', function () {
    Queue::fake();

    $intake = DocumentIntake::factory()->done()->create([
        'document_id' => null,
    ]);

    $response = $this
        ->actingAs($intake->user)
        ->postJson(route('documents.intake.retry', $intake));

    $response
        ->assertOk()
        ->assertJsonFragment([
            'id' => $intake->id,
            'status' => DocumentIntake::STATUS_QUEUED,
        ]);

    $intake->refresh();

    expect($intake->status)->toBe(DocumentIntake::STATUS_QUEUED);

    Queue::assertPushed(ProcessDocumentIntake::class);
});

test('pdf intake generates page images and thumbnails', function () {
    if (! class_exists(Imagick::class)) {
        $this->markTestSkipped('Imagick is not available.');
    }

    Storage::fake('private');

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

    $intake = DocumentIntake::factory()->create();
    $pdfPath = makeTestPdfPath();

    if ($pdfPath === '') {
        $this->markTestSkipped('Unable to generate PDF content.');
    }

    $intake->addMedia($pdfPath)
        ->usingFileName('scan.pdf')
        ->toMediaCollection('scans');

    app(AnalyzeDocumentIntake::class)($intake, collect());

    $intake->refresh();

    $pages = $intake->getMedia('pages');

    expect($pages)->not->toBeEmpty()
        ->and($pages->count())->toBeLessThanOrEqual(10);

    $firstPage = $pages->first();

    expect($firstPage)->not->toBeNull()
        ->and(file_exists($firstPage->getPath('thumb')))->toBeTrue();
});

test('pdf page thumbnails are available after moving to document', function () {
    if (! class_exists(Imagick::class)) {
        $this->markTestSkipped('Imagick is not available.');
    }

    Storage::fake('private');

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

    $intake = DocumentIntake::factory()->create();
    $pdfPath = makeTestPdfPath();

    if ($pdfPath === '') {
        $this->markTestSkipped('Unable to generate PDF content.');
    }

    $intake->addMedia($pdfPath)
        ->usingFileName('scan.pdf')
        ->toMediaCollection('scans');

    ProcessDocumentIntake::dispatchSync($intake);

    $document = Document::query()->latest()->first();

    expect($document)->not->toBeNull();

    $document?->refresh();

    $pages = $document?->getMedia('pages') ?? collect();

    expect($pages)->not->toBeEmpty();

    $firstPage = $pages->first();

    expect($firstPage)->not->toBeNull()
        ->and(file_exists($firstPage->getPath('thumb')))->toBeTrue();
});

test('job failure marks intake as failed', function () {
    $intake = DocumentIntake::factory()->processing()->create();
    $job = new ProcessDocumentIntake($intake);

    $job->failed(new Exception('Boom'));

    $intake->refresh();

    expect($intake->status)->toBe(DocumentIntake::STATUS_FAILED)
        ->and($intake->error_message)->toBe('Boom')
        ->and($intake->finished_at)->not->toBeNull();
});

function makeTestPdfPath(): string
{
    if (! class_exists(Imagick::class)) {
        return '';
    }

    $tempBase = tempnam(sys_get_temp_dir(), 'pdf-');

    if ($tempBase === false) {
        return '';
    }

    $pdfPath = $tempBase.'.pdf';
    @unlink($tempBase);

    $imagick = new Imagick;
    $imagick->newImage(200, 200, new ImagickPixel('white'));
    $imagick->setImageFormat('pdf');
    $imagick->writeImage($pdfPath);
    $imagick->clear();
    $imagick->destroy();

    return file_exists($pdfPath) ? $pdfPath : '';
}
