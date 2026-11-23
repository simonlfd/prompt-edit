<?php

use App\Http\Controllers\Admin\SpendReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Authenticated by signature, not session; excluded from CSRF in bootstrap/app.php.
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GenerationController::class, 'index'])->name('dashboard');
    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('generations.store');
    Route::get('generations/estimate', EstimateController::class)
        ->middleware('throttle:240,1')
        ->name('generations.estimate');

    Route::get('gallery', GalleryController::class)->name('gallery');
    Route::put('generations/{generation}/favourite', [FavouriteController::class, 'update'])
        ->name('generations.favourite');
    Route::get('generations/{generation}/download', DownloadController::class)
        ->middleware('throttle:30,1')
        ->name('generations.download');

    Route::post('credits', [CreditController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('credits.store');

    Route::get('admin/report', SpendReportController::class)
        ->middleware('can:admin')
        ->name('admin.report');
});

require __DIR__.'/settings.php';
