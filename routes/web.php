<?php

use App\Http\Middleware\MarkStorefrontRequest;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

// Marks these routes as rendering the storefront chrome, which is what tells
// HandleInertiaRequests to attach the navbar's collection links - so the
// dashboard/settings/auth routes never run that query.
Route::middleware(MarkStorefrontRequest::class)->group(__DIR__.'/storefront.php');
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
