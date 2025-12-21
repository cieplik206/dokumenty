<?php

use App\Jobs\ProcessDocumentIntake;
use App\Models\DocumentIntake;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('document intake queues analysis', function () {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create();
    $scan = UploadedFile::fake()->image('scan.png', 10, 10);

    $response = $this
        ->actingAs($user)
        ->post(route('documents.intake'), [
            'scans' => [$scan],
        ]);

    $response->assertAccepted();

    $intake = DocumentIntake::query()->first();

    expect($intake)->not->toBeNull()
        ->and($intake->status)->toBe(DocumentIntake::STATUS_QUEUED);

    Queue::assertPushed(ProcessDocumentIntake::class, function (ProcessDocumentIntake $job) use ($intake) {
        return $job->intake->is($intake);
    });
});

test('document intake status returns payload', function () {
    $intake = DocumentIntake::factory()->done()->create();

    $response = $this
        ->actingAs($intake->user)
        ->getJson(route('documents.intake.show', $intake));

    $response
        ->assertOk()
        ->assertJson([
            'id' => $intake->id,
            'status' => DocumentIntake::STATUS_DONE,
        ]);
});
