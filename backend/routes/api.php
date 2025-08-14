<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/posts', [\App\Http\Controllers\PostController::class, 'store'])
    ->name('posts.store')
    ->middleware('auth:sanctum');
