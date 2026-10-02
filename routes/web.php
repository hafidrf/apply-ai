<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all: serve frontend build dari public/ (semua path non-/api)
Route::get('/{any}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '^(?!api).*$');
