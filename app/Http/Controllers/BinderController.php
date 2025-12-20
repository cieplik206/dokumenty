<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentIndexRequest;
use App\Http\Requests\StoreBinderRequest;
use App\Http\Requests\UpdateBinderRequest;
use App\Models\Binder;
use App\Models\Category;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BinderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $binders = Binder::query()
            ->withCount('documents')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Binder $binder) => [
                'id' => $binder->id,
                'name' => $binder->name,
                'location' => $binder->location,
                'description' => $binder->description,
                'documents_count' => $binder->documents_count,
                'sort_order' => $binder->sort_order,
            ]);

        return Inertia::render('binders/Index', [
            'binders' => $binders,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('binders/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBinderRequest $request): RedirectResponse
    {
        $binder = Binder::create($request->validated());

        return redirect()->route('binders.show', $binder);
    }

    /**
     * Display the specified resource.
     */
    public function show(Binder $binder, DocumentIndexRequest $request): Response
    {
        $filters = $request->validated();

        $documents = Document::query()
            ->with(['binder', 'category'])
            ->withCount('media')
            ->where('binder_id', $binder->id)
            ->filter($filters)
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
            ]);

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('binders/Show', [
            'binder' => [
                'id' => $binder->id,
                'name' => $binder->name,
                'location' => $binder->location,
                'description' => $binder->description,
                'documents_count' => $binder->documents()->count(),
            ],
            'documents' => $documents,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Binder $binder): Response
    {
        return Inertia::render('binders/Edit', [
            'binder' => [
                'id' => $binder->id,
                'name' => $binder->name,
                'location' => $binder->location,
                'description' => $binder->description,
                'sort_order' => $binder->sort_order,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBinderRequest $request, Binder $binder): RedirectResponse
    {
        $binder->update($request->validated());

        return redirect()->route('binders.show', $binder);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Binder $binder): RedirectResponse
    {
        $binder->delete();

        return redirect()->route('binders.index');
    }
}
