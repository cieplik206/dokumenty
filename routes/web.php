<?php

use App\Http\Controllers\BinderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentIntakeController;
use App\Http\Controllers\DocumentMediaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('dashboard', fn () => redirect()->route('dashboard'))
    ->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('binders', BinderController::class);

    Route::post('documents/intake', [DocumentIntakeController::class, 'store'])
        ->name('documents.intake');
    Route::get('documents/intake', [DocumentIntakeController::class, 'index'])
        ->name('documents.intake.index');
    Route::post('documents/intake/{intake}/start', [DocumentIntakeController::class, 'start'])
        ->name('documents.intake.start');
    Route::post('documents/intake/{intake}/finalize', [DocumentIntakeController::class, 'finalize'])
        ->name('documents.intake.finalize');
    Route::post('documents/intake/{intake}/retry', [DocumentIntakeController::class, 'retry'])
        ->name('documents.intake.retry');
    Route::delete('documents/intake', [DocumentIntakeController::class, 'destroyBulk'])
        ->name('documents.intake.destroy.bulk');
    Route::delete('documents/intake/{intake}', [DocumentIntakeController::class, 'destroy'])
        ->name('documents.intake.destroy');

    Route::get('documents/grid', [DocumentController::class, 'indexGrid'])
        ->name('documents.index.grid');
    Route::get('documents/table', [DocumentController::class, 'indexTable'])
        ->name('documents.index.table');
    Route::get('documents/compact', [DocumentController::class, 'indexCompact'])
        ->name('documents.index.compact');

    Route::resource('documents', DocumentController::class);

    Route::get('documents/{document}/media/{media}', [DocumentMediaController::class, 'download'])
        ->name('documents.media.download');
    Route::delete('documents/{document}/media/{media}', [DocumentMediaController::class, 'destroy'])
        ->name('documents.media.destroy');

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });
});

require __DIR__.'/settings.php';
