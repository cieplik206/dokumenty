<?php

use App\Http\Controllers\BinderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentMediaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('binders', BinderController::class);
    Route::resource('documents', DocumentController::class);

    Route::get('documents/{document}/media/{media}', [DocumentMediaController::class, 'download'])
        ->name('documents.media.download');
    Route::delete('documents/{document}/media/{media}', [DocumentMediaController::class, 'destroy'])
        ->name('documents.media.destroy');
});

require __DIR__.'/settings.php';
