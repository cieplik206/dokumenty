<?php

namespace App\Http\Controllers;

use App\Actions\Documents\DocumentIntakePageGenerator;
use App\Http\Requests\DocumentIntakeBulkDestroyRequest;
use App\Http\Requests\DocumentIntakeFinalizeRequest;
use App\Http\Requests\DocumentIntakeIndexRequest;
use App\Http\Requests\DocumentIntakeRequest;
use App\Jobs\ProcessDocumentIntake;
use App\Models\Document;
use App\Models\DocumentIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class DocumentIntakeController extends Controller
{
    /**
     * Store a newly created intake request.
     */
    public function store(DocumentIntakeRequest $request, DocumentIntakePageGenerator $pageGenerator): JsonResponse
    {
        $user = $request->user();
        $intakes = collect();

        foreach ($request->file('scans', []) as $scan) {
            $intake = DocumentIntake::create([
                'user_id' => $user->id,
                'status' => DocumentIntake::STATUS_UPLOADED,
                'original_name' => $scan->getClientOriginalName(),
            ]);

            $media = $intake->addMedia($scan)
                ->toMediaCollection('scans');

            $pageGenerator->ensurePages($intake, $media, DocumentIntakePageGenerator::MAX_PDF_PAGES);

            $intakes->push($intake->refresh());
        }

        return response()->json([
            'items' => $intakes->map(fn (DocumentIntake $intake) => $this->formatIntake($intake)),
        ], 202);
    }

    /**
     * Display intake statuses for polling.
     */
    public function index(DocumentIntakeIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $ids = $this->parseIds($request->validated('ids'));

        if ($ids->isEmpty()) {
            return response()->json(['items' => []]);
        }

        $intakes = DocumentIntake::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids->all())
            ->get();

        return response()->json([
            'items' => $intakes->map(fn (DocumentIntake $intake) => $this->formatIntake($intake)),
        ]);
    }

    /**
     * Finalize document storage type.
     */
    public function finalize(DocumentIntakeFinalizeRequest $request, DocumentIntake $intake): JsonResponse
    {
        if ($intake->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($intake->status !== DocumentIntake::STATUS_DONE || ! $intake->document_id) {
            return response()->json([
                'message' => 'Analiza jeszcze sie nie zakonczyla.',
            ], 409);
        }

        $document = $intake->document;

        if (! $document instanceof Document) {
            return response()->json([
                'message' => 'Nie znaleziono dokumentu do edycji.',
            ], 404);
        }

        $data = $request->validated();
        $storageType = $data['storage_type'];
        $binderId = $storageType === 'paper' ? $data['binder_id'] : null;

        $document->update([
            'binder_id' => $binderId,
            'status' => Document::STATUS_READY,
        ]);

        $intake->forceFill([
            'storage_type' => $storageType,
            'status' => DocumentIntake::STATUS_FINALIZED,
            'finalized_at' => now(),
        ])->save();

        return response()->json($this->formatIntake($intake->refresh()));
    }

    /**
     * Start processing an uploaded intake.
     */
    public function start(Request $request, DocumentIntake $intake): JsonResponse
    {
        if ($intake->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($intake->status !== DocumentIntake::STATUS_UPLOADED) {
            return response()->json([
                'message' => 'Analiza moze zostac uruchomiona tylko dla nowych skanow.',
            ], 409);
        }

        $intake->forceFill([
            'status' => DocumentIntake::STATUS_QUEUED,
            'error_message' => null,
            'fields' => null,
            'extracted_text' => null,
            'extracted_content' => null,
            'ai_metadata' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        ProcessDocumentIntake::dispatch($intake);

        return response()->json($this->formatIntake($intake->refresh()));
    }

    /**
     * Retry a failed intake.
     */
    public function retry(Request $request, DocumentIntake $intake): JsonResponse
    {
        if ($intake->user_id !== $request->user()->id) {
            abort(404);
        }

        $canRetry = $intake->status === DocumentIntake::STATUS_FAILED
            || ($intake->status === DocumentIntake::STATUS_DONE && $intake->document_id === null);

        if (! $canRetry) {
            return response()->json([
                'message' => 'Tylko nieudane lub niekompletne analizy mozna ponowic.',
            ], 409);
        }

        $intake->forceFill([
            'status' => DocumentIntake::STATUS_QUEUED,
            'error_message' => null,
            'fields' => null,
            'extracted_text' => null,
            'extracted_content' => null,
            'ai_metadata' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        ProcessDocumentIntake::dispatch($intake);

        return response()->json($this->formatIntake($intake->refresh()));
    }

    /**
     * Remove intake requests in bulk.
     */
    public function destroyBulk(DocumentIntakeBulkDestroyRequest $request): JsonResponse
    {
        $user = $request->user();
        $ids = $request->validated('ids');

        $intakes = DocumentIntake::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get();

        foreach ($intakes as $intake) {
            $this->deleteIntake($intake);
        }

        return response()->json([], 204);
    }

    /**
     * Remove an intake request.
     */
    public function destroy(Request $request, DocumentIntake $intake): JsonResponse
    {
        if ($intake->user_id !== $request->user()->id) {
            abort(404);
        }

        $this->deleteIntake($intake);

        return response()->json([], 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatIntake(?DocumentIntake $intake): array
    {
        if (! $intake instanceof DocumentIntake) {
            return [];
        }

        $pages = $this->resolvePreviewPages($intake);
        $scans = $intake->getMedia('scans');

        if ($scans->isEmpty() && $intake->document instanceof Document) {
            $scans = $intake->document->getMedia('scans');
        }

        return [
            'id' => $intake->id,
            'status' => $intake->status,
            'document_id' => $intake->document_id,
            'original_name' => $intake->original_name,
            'title' => data_get($intake->fields, 'title'),
            'storage_type' => $intake->storage_type,
            'preview_url' => $pages[0]['thumb_url'] ?? null,
            'preview_full_url' => $pages[0]['url'] ?? null,
            'pages' => $pages,
            'scans_count' => $scans->count(),
            'scans_size' => $scans->sum('size'),
            'fields' => $intake->fields,
            'extracted_text' => $intake->extracted_text,
            'extracted_content' => $intake->extracted_content,
            'metadata' => $intake->ai_metadata,
            'error_message' => $intake->error_message,
            'started_at' => $intake->started_at?->toISOString(),
            'finished_at' => $intake->finished_at?->toISOString(),
            'finalized_at' => $intake->finalized_at?->toISOString(),
            'created_at' => $intake->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{id:int, page:int, url:string, thumb_url:string|null}>
     */
    private function resolvePreviewPages(DocumentIntake $intake): array
    {
        $expiration = now()->addMinutes(30);
        $pages = $intake->getMedia('pages')
            ->sortBy(fn (Media $page) => (int) $page->getCustomProperty('page'))
            ->values();

        if ($pages->isEmpty() && $intake->document instanceof Document) {
            $pages = $intake->document->getMedia('pages')
                ->sortBy(fn (Media $page) => (int) $page->getCustomProperty('page'))
                ->values();
        }

        if ($pages->isEmpty()) {
            $pages = $intake->getMedia('scans')
                ->filter(fn (Media $item) => Str::startsWith((string) $item->mime_type, 'image/'))
                ->values();
        }

        if ($pages->isEmpty() && $intake->document instanceof Document) {
            $pages = $intake->document->getMedia('scans')
                ->filter(fn (Media $item) => Str::startsWith((string) $item->mime_type, 'image/'))
                ->values();
        }

        return $pages->map(function (Media $media, int $index) use ($expiration) {
            $pageNumber = (int) $media->getCustomProperty('page', $index + 1);
            $url = $this->resolveMediaUrl($media, $expiration);
            $thumbUrl = $media->hasGeneratedConversion('thumb')
                ? $this->resolveMediaUrl($media, $expiration, 'thumb')
                : $url;

            return [
                'id' => $media->id,
                'page' => $pageNumber,
                'url' => $url,
                'thumb_url' => $thumbUrl !== $url ? $thumbUrl : null,
            ];
        })->all();
    }

    private function resolveMediaUrl(Media $media, \DateTimeInterface $expiration, string $conversionName = ''): string
    {
        try {
            if (Storage::disk($media->disk)->providesTemporaryUrls()) {
                return $media->getTemporaryUrl($expiration, $conversionName);
            }
        } catch (Throwable) {
            // Fallback below when disk doesn't support temporary URLs in tests.
        }

        return $media->getUrl($conversionName);
    }

    /**
     * @return Collection<int, int>
     */
    private function parseIds(?string $raw): Collection
    {
        if ($raw === null || trim($raw) === '') {
            return collect();
        }

        return collect(explode(',', $raw))
            ->map(fn (string $id) => trim($id))
            ->filter(fn (string $id) => $id !== '' && ctype_digit($id))
            ->map(fn (string $id) => (int) $id)
            ->values();
    }

    private function deleteIntake(DocumentIntake $intake): void
    {
        if ($intake->document instanceof Document && $intake->document->status === Document::STATUS_DRAFT) {
            $intake->document->delete();
        }

        $intake->clearMediaCollection('scans');
        $intake->delete();
    }
}
