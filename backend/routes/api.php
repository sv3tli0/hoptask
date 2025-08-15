<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/token', function (Request $request) {
    // Create a demo user for token generation
    $user = User::query()->first() ?? User::factory(1)->create();

    $tokenWithAbility = $user->createToken('token-with-create-posts', ['create_posts'])->plainTextToken;
    $tokenWithoutAbility = $user->createToken('token-without-create-posts', [])->plainTextToken;

    return response()->json([
        'token_with_create_posts' => $tokenWithAbility,
        'token_without_create_posts' => $tokenWithoutAbility,
    ]);
});

Route::get('/posts', [\App\Http\Controllers\PostController::class, 'index'])
    ->name('posts.index');

Route::post('/posts', [\App\Http\Controllers\PostController::class, 'store'])
    ->name('posts.store')
    ->middleware('auth:sanctum');
