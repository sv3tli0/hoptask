<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/posts', [\App\Http\Controllers\PostController::class, 'index'])
    ->name('posts.index');

Route::post('/posts', [\App\Http\Controllers\PostController::class, 'store'])
    ->name('posts.store')
    ->middleware('auth:sanctum');
