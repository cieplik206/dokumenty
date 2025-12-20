<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentMediaController extends Controller
{
    public function download(Document $document, Media $media): BinaryFileResponse
    {
        $this->ensureMediaBelongsToDocument($document, $media);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function destroy(Document $document, Media $media): RedirectResponse
    {
        $this->ensureMediaBelongsToDocument($document, $media);

        $media->delete();

        return redirect()->back();
    }

    private function ensureMediaBelongsToDocument(Document $document, Media $media): void
    {
        if ($media->model_type !== Document::class || $media->model_id !== $document->id) {
            abort(404);
        }
    }
}
