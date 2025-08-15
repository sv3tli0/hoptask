<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PostModerationSeverity;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostModeration;
use App\Services\GeminiModerationService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ModeratePostContentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public private(set) readonly int $postId,
    ) {
        $this->onQueue('moderation');
    }

    public function handle(GeminiModerationService $moderationService): void
    {
        $post = Post::query()->findOrFail($this->postId);

        try {
            $moderationResult = $moderationService->moderate($post->content);

            if ($moderationResult['error'] ?? false) {
                throw new Exception('Gemini moderation service unavailable: '.($moderationResult['reason'] ?? 'Unknown error'));
            }

            $postModeration = PostModeration::query()
                ->create([
                    'post_id' => $post->id,
                    'approved' => $moderationResult['approved'],
                    'categories' => $moderationResult['categories'] ?? null,
                    'severity' => PostModerationSeverity::from($moderationResult['severity'] ?? 'medium'),
                    'confidence' => $moderationResult['confidence'] ?? null,
                    'reason' => $moderationResult['reason'],
                    'error' => false,
                ]);

            $post->update([
                'status' => $moderationResult['approved'] ? PostStatus::Approved : PostStatus::Rejected,
                'moderation_reason' => $moderationResult['approved'] ? null : $moderationResult['reason'],
            ]);

            WebSocketNotifyJob::dispatch('post_moderated', $post->toWebSocketArray())
                ->delay(now()->addSeconds(1))
                ->onQueue('websocket');

            Log::debug('Post moderation completed successfully', [
                'post_id' => $post->id,
                'approved' => $moderationResult['approved'],
                'moderation_id' => $postModeration->id,
                'attempt' => $this->attempts(),
            ]);
        } catch (Exception $e) {
            Log::warning('Post moderation attempt failed', [
                'post_id' => $post->id,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                PostModeration::query()->create([
                    'post_id' => $post->id,
                    'approved' => false,
                    'categories' => null,
                    'severity' => PostModerationSeverity::Medium,
                    'confidence' => null,
                    'reason' => 'Moderation service failed after '.$this->tries.' attempts',
                    'error' => true,
                ]);

                $post->update([
                    'status' => PostStatus::Rejected,
                    'moderation_reason' => 'Content could not be moderated due to service issues',
                ]);

                WebSocketNotifyJob::dispatch('post_moderation_failed', $post->toWebSocketArray())
                    ->onQueue('websocket');

                Log::error('Post moderation permanently failed', [
                    'post_id' => $post->id,
                    'final_error' => $e->getMessage(),
                ]);
            } else {
                throw $e;
            }
        }
    }
}
