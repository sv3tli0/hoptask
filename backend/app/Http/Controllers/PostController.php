<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Jobs\ModeratePostContentJob;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'posts' => Post::query()
                ->with('moderations')
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Post created successfully',
                'post' => Post::query()->create($request->validated()),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
