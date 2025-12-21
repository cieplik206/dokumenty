<?php

use App\Http\Controllers\BinderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentIntakeController;
use App\Http\Controllers\DocumentMediaController;
use App\Models\Document;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard', [
        'stats' => [
            'documentsCount' => Document::count(),
            'totalSize' => Media::sum('size'),
            'lastDocumentAt' => Document::latest()->first()?->created_at,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('binders', BinderController::class);
    Route::resource('documents', DocumentController::class);

    Route::post('documents/intake', [DocumentIntakeController::class, 'store'])
        ->name('documents.intake');
    Route::get('documents/intake/{intake}', [DocumentIntakeController::class, 'show'])
        ->name('documents.intake.show');

    Route::get('documents/{document}/media/{media}', [DocumentMediaController::class, 'download'])
        ->name('documents.media.download');
    Route::delete('documents/{document}/media/{media}', [DocumentMediaController::class, 'destroy'])
        ->name('documents.media.destroy');
});

require __DIR__.'/settings.php';
