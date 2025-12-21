<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentIndexRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Binder;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(DocumentIndexRequest $request): Response
    {
        return $this->renderIndex($request, 'documents/Index');
    }

    /**
     * Display documents in grid view.
     */
    public function indexGrid(DocumentIndexRequest $request): Response
    {
        return $this->renderIndex($request, 'documents/IndexGrid');
    }

    /**
     * Display documents in table view.
     */
    public function indexTable(DocumentIndexRequest $request): Response
    {
        return $this->renderIndex($request, 'documents/IndexTable');
    }

    /**
     * Display documents in compact view.
     */
    public function indexCompact(DocumentIndexRequest $request): Response
    {
        return $this->renderIndex($request, 'documents/IndexCompact');
    }

    /**
     * Common method to render document index views.
     */
    private function renderIndex(DocumentIndexRequest $request, string $component): Response
    {
        $filters = $request->validated();

        $documents = Document::query()
            ->with(['binder', 'category'])
            ->withCount('media')
            ->filter($filters)
            ->where('status', Document::STATUS_READY)
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'issuer' => $document->issuer,
                'category' => $document->category?->name,
                'category_id' => $document->category_id,
                'document_date' => $document->document_date?->toDateString(),
                'received_at' => $document->received_at?->toDateString(),
                'media_count' => $document->media_count,
                'thumbnail_url' => $this->resolveDocumentPreviewUrl($document),
                'binder' => $document->binder
                    ? [
                        'id' => $document->binder->id,
                        'name' => $document->binder->name,
                    ]
                    : null,
            ]);

        $binders = Binder::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render($component, [
            'documents' => $documents,
            'binders' => $binders,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $binders = Binder::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $intakes = DocumentIntake::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [
                DocumentIntake::STATUS_UPLOADED,
                DocumentIntake::STATUS_QUEUED,
                DocumentIntake::STATUS_PROCESSING,
                DocumentIntake::STATUS_DONE,
                DocumentIntake::STATUS_FAILED,
                DocumentIntake::STATUS_FINALIZED,
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('documents/Create', [
            'binders' => $binders,
            'initialIntakes' => $intakes->map(
                fn (DocumentIntake $intake) => $this->formatIntake($intake),
            ),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $scans = $request->file('scans', []);
        $intake = null;

        if (! empty($data['intake_id'])) {
            $intake = DocumentIntake::query()
                ->whereKey($data['intake_id'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $intake) {
                return back()
                    ->withErrors(['scans' => 'Nie znaleziono paczki skanow do analizy.'])
                    ->withInput();
            }

            if ($intake->status !== DocumentIntake::STATUS_DONE) {
                return back()
                    ->withErrors(['scans' => 'Poczekaj na zakonczenie analizy skanu.'])
                    ->withInput();
            }
        }

        $isPaper = filter_var($data['is_paper'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! $isPaper) {
            $data['binder_id'] = null;
        }

        unset($data['scans'], $data['is_paper'], $data['intake_id']);

        if ($intake instanceof DocumentIntake) {
            $data['extracted_content'] ??= $intake->extracted_content;
            $data['ai_metadata'] ??= $intake->ai_metadata;
            $scans = [];
        }

        $document = Document::create([
            ...$data,
            'status' => Document::STATUS_READY,
        ]);

        if ($intake instanceof DocumentIntake) {
            foreach ($intake->getMedia('scans') as $media) {
                $media->move($document, 'scans');
            }

            $intake->delete();
        }

        foreach ($scans as $scan) {
            $document->addMedia($scan)
                ->toMediaCollection('scans');
        }

        return redirect()->route('documents.show', $document);
    }

    /**
     * Display the specified resource.
     */
    public function show(Document $document): Response
    {
        $document->load('binder', 'category', 'media');
        $pages = $this->resolveDocumentPages($document);

        $scans = $document->getMedia('scans')
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'size' => $media->size,
                'mime_type' => $media->mime_type,
                'created_at' => $media->created_at?->toDateTimeString(),
                'download_url' => route('documents.media.download', [
                    'document' => $document,
                    'media' => $media,
                ], absolute: false),
                'delete_url' => route('documents.media.destroy', [
                    'document' => $document,
                    'media' => $media,
                ], absolute: false),
            ]);

        return Inertia::render('documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'issuer' => $document->issuer,
                'category' => $document->category?->name,
                'category_id' => $document->category_id,
                'document_date' => $document->document_date?->toDateString(),
                'received_at' => $document->received_at?->toDateString(),
                'notes' => $document->notes,
                'tags' => $document->tags,
                'binder' => $document->binder
                    ? [
                        'id' => $document->binder->id,
                        'name' => $document->binder->name,
                        'location' => $document->binder->location,
                    ]
                    : null,
                'pages' => $pages,
                'scans' => $scans,
            ],
        ]);
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

    private function resolveDocumentPreviewUrl(Document $document): ?string
    {
        $expiration = now()->addMinutes(30);

        $pages = $document->getMedia('pages')
            ->sortBy(fn (Media $page) => (int) $page->getCustomProperty('page'))
            ->values();

        $media = $pages->first();

        if (! $media) {
            $media = $document->getMedia('scans')
                ->first(fn (Media $item) => Str::startsWith((string) $item->mime_type, 'image/'));
        }

        if (! $media) {
            return null;
        }

        $conversion = $media->hasGeneratedConversion('thumb') ? 'thumb' : '';

        return $this->resolveMediaUrl($media, $expiration, $conversion);
    }

    /**
     * @return array<int, array{id:int, page:int, url:string, thumb_url:string|null}>
     */
    private function resolveDocumentPages(Document $document): array
    {
        $expiration = now()->addMinutes(30);
        $pages = $document->getMedia('pages')
            ->sortBy(fn (Media $page) => (int) $page->getCustomProperty('page'))
            ->values();

        if ($pages->isEmpty()) {
            $pages = $document->getMedia('scans')
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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Document $document): Response
    {
        $document->load('binder', 'category', 'media');

        $binders = Binder::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('documents/Edit', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'issuer' => $document->issuer,
                'category' => $document->category?->name,
                'category_id' => $document->category_id,
                'document_date' => $document->document_date?->toDateString(),
                'received_at' => $document->received_at?->toDateString(),
                'notes' => $document->notes,
                'tags' => $document->tags,
                'binder_id' => $document->binder_id,
                'scans' => $document->getMedia('scans')->map(fn (Media $media) => [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'created_at' => $media->created_at?->toDateTimeString(),
                    'download_url' => route('documents.media.download', [
                        'document' => $document,
                        'media' => $media,
                    ], absolute: false),
                    'delete_url' => route('documents.media.destroy', [
                        'document' => $document,
                        'media' => $media,
                    ], absolute: false),
                ]),
            ],
            'binders' => $binders,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $data = $request->validated();
        $scans = $request->file('scans', []);

        $isPaper = filter_var($data['is_paper'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! $isPaper) {
            $data['binder_id'] = null;
        }

        unset($data['scans'], $data['is_paper']);

        $document->update($data);

        foreach ($scans as $scan) {
            $document->addMedia($scan)
                ->toMediaCollection('scans');
        }

        return redirect()->route('documents.show', $document);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();

        return redirect()->route('documents.index');
    }
}
