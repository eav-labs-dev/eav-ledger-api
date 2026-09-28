<?php

use Illuminate\Support\Facades\Route;

Route::get('/docs', function () {
    return response()->file(public_path('docs/index.html'));
})->name('docs');
