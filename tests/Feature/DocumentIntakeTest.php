<?php

use App\Jobs\ProcessDocumentIntake;
use App\Models\Binder;
use App\Models\Document;
use App\Models\DocumentIntake;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('document intake queues analysis for each file', function () {
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
        ->and($intakes->first()->status)->toBe(DocumentIntake::STATUS_QUEUED);

    Queue::assertPushed(ProcessDocumentIntake::class, 2);
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
