<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::query()->create($request->validated());

        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post,
        ], 201);
    }
}
