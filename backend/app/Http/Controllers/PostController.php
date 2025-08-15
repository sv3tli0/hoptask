<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Jobs\ModeratePostContentJob;
use App\Jobs\WebSocketNotifyJob;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
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
        if (! $request->user()->tokenCan('create_posts')) {
            return response()->json([
                'message' => 'Insufficient permissions to create posts',
            ], 403);
        }

        $post = Post::query()->create($request->validated());

        defer(function () use ($post) {
            WebSocketNotifyJob::dispatch('post_created', $post->toWebSocketArray())->onQueue('websocket');
            ModeratePostContentJob::dispatch($post->id)->onQueue('moderation');
        });

        return response()->json([
            'message' => 'Post created successfully',
            'post' => $post,
        ], 201);
    }
}
