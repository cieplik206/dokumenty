<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'documentsCount' => Document::count(),
                'totalSize' => Media::sum('size'),
                'lastDocumentAt' => Document::latest()->first()?->created_at,
            ],
        ]);
    }
}
