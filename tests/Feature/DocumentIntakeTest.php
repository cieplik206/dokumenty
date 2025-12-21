<?php

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Str;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

test('document intake streams ai response', function () {
    $fake = Prism::fake([
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
    $csrfToken = Str::random(40);
    $encryptedToken = app('encrypter')->encrypt(
        CookieValuePrefix::create('XSRF-TOKEN', app('encrypter')->getKey()).$csrfToken,
        EncryptCookies::serialized('XSRF-TOKEN')
    );

    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';

    $response = $this
        ->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->withHeader('X-XSRF-TOKEN', $encryptedToken)
        ->postJson(route('documents.intake'), [
            'messages' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'type' => 'file',
                            'url' => "data:image/png;base64,{$pngBase64}",
                            'mediaType' => 'image/png',
                        ],
                    ],
                ],
            ],
        ]);

    $response->assertSuccessful();
    $response->assertHeader('x-vercel-ai-ui-message-stream', 'v1');

    $streamedContent = $response->streamedContent();

    $fake->assertRequest(function (array $requests): void {
        expect($requests)->toHaveCount(1);
        expect($requests[0]->clientOptions())->toMatchArray(['timeout' => 300]);
    });

    expect($streamedContent)
        ->toContain('"text-delta"')
        ->toContain('[DONE]');
});
