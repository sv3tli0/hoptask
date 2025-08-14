<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Jobs\ModeratePostContentJob;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::query()->create($request->validated());

        ModeratePostContentJob::dispatch($post->id)
            ->onQueue('moderation');// not actually needed as we have set default queue within the job class

        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post,
        ], 201);
    }
}
