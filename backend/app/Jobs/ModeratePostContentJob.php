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

class ModeratePostContentJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public int $tries = 5;
    public int $maxExceptions = 3;
    public array $backoff = [30, 60, 120, 300]; // Retry after 30s, 1m, 2m, 5m

    public function __construct(
        public private(set) readonly int $postId,
    ) {
        $this->onQueue('moderation');
    }

    public function handle(GeminiModerationService $moderationService): void
    {
        $post = Post::query()
            ->findOrFail($this->postId);

        try {
            $moderationResult = $moderationService->moderate($post->content);
            
            // Check if service returned an error
            if ($moderationResult['error'] ?? false) {
                throw new Exception('Gemini moderation service unavailable: ' . ($moderationResult['reason'] ?? 'Unknown error'));
            }

            // Store moderation result in PostModeration model
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

            logger()->info('Post moderation completed successfully', [
                'post_id' => $post->id,
                'approved' => $moderationResult['approved'],
                'moderation_id' => $postModeration->id,
                'attempt' => $this->attempts(),
            ]);

        } catch (Exception $e) {
            logger()->warning('Post moderation attempt failed', [
                'post_id' => $post->id,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $e->getMessage(),
            ]);

            // If this is the final attempt, create a failed moderation record
            if ($this->attempts() >= $this->tries) {
                PostModeration::query()->create([
                    'post_id' => $post->id,
                    'approved' => false,
                    'categories' => null,
                    'severity' => PostModerationSeverity::Medium,
                    'confidence' => null,
                    'reason' => 'Moderation service failed after ' . $this->tries . ' attempts',
                    'error' => true,
                ]);

                $post->update([
                    'status' => PostStatus::Rejected,
                    'moderation_reason' => 'Content could not be moderated due to service issues',
                ]);

                logger()->error('Post moderation permanently failed', [
                    'post_id' => $post->id,
                    'final_error' => $e->getMessage(),
                ]);
            } else {
                // Re-throw the exception to trigger retry
                throw $e;
            }
        }
    }

}
