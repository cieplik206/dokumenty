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
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(DocumentIndexRequest $request): Response
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

        return Inertia::render('documents/Index', [
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

        return [
            'id' => $intake->id,
            'status' => $intake->status,
            'document_id' => $intake->document_id,
            'original_name' => $intake->original_name,
            'storage_type' => $intake->storage_type,
            'error_message' => $intake->error_message,
            'started_at' => $intake->started_at?->toISOString(),
            'finished_at' => $intake->finished_at?->toISOString(),
            'finalized_at' => $intake->finalized_at?->toISOString(),
            'created_at' => $intake->created_at?->toISOString(),
        ];
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
