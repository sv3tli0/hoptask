<?php

namespace App\Observers;

use App\Jobs\ModeratePostContentJob;
use App\Jobs\WebSocketNotifyJob;
use App\Models\Post;

class PostObserver
{
    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void
    {
        WebSocketNotifyJob::dispatch('post_created', $post->toWebSocketArray())->onQueue('websocket');
        ModeratePostContentJob::dispatch($post->id)->onQueue('moderation');
    }

    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void
    {
        defer(function () use ($post) {
            WebSocketNotifyJob::dispatch('post_updated', $post->toWebSocketArray())
                ->delay(now()->addSeconds(2)) // Delay to ensure the post is fully updated before notifying
                ->onQueue('websocket');
        });
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "restored" event.
     */
    public function restored(Post $post): void
    {
        //
    }

    /**
     * Handle the Post "force deleted" event.
     */
    public function forceDeleted(Post $post): void
    {
        //
    }
}
