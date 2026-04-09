<?php

use App\Http\Controllers\Internal\ArticlesController;
use App\Http\Controllers\Internal\FetchJobController;
use Illuminate\Support\Facades\Route;

Route::middleware('internal')->prefix('internal')->group(function () {
    Route::post('/jobs/fetch', [FetchJobController::class, 'store']);
    Route::get('/jobs/{id}', [FetchJobController::class, 'show']);

    Route::get('/articles', [ArticlesController::class, 'index']);
    Route::get('/articles/{id}', [ArticlesController::class, 'show']);
});
