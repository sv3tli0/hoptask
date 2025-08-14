<?php

declare(strict_types=1);

use App\Enums\PostModerationSeverity;
use App\Enums\PostStatus;
use App\Jobs\ModeratePostContentJob;
use App\Models\Post;
use App\Models\PostModeration;
use App\Models\User;
use App\Services\GeminiModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(User::factory()->create(), ['create_posts']));

describe('Post Validation Tests', function () {
    it('rejects post without content', function () {
        Http::fake();

        $this->postJson('/api/posts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        expect(Post::count())->toBe(0);
    });

    it('rejects empty content', function () {
        Http::fake();

        $this->postJson('/api/posts', ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        expect(Post::count())->toBe(0);
    });

    it('rejects content that is too long', function () {
        Http::fake();

        $longContent = str_repeat('a', 10001); // Exceeds max length of 10000

        $this->postJson('/api/posts', ['content' => $longContent])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        expect(Post::count())->toBe(0);
    });
});

describe('Post Creation Flow Tests', function () {
    it('creates post successfully and dispatches moderation job', function () {
        Queue::fake();
        Http::fake();

        $content = 'This is a valid test post content';

        $response = $this->postJson('/api/posts', ['content' => $content])
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Post created successfully',
            ]);

        $post = Post::first();
        expect($post)->not->toBeNull()
            ->and($post->content)->toBe($content)
            ->and($post->status)->toBe(PostStatus::Pending);

        Queue::assertPushed(ModeratePostContentJob::class, function ($job) use ($post) {
            return $job->postId === $post->id;
        });

        $responseData = $response->json();
        expect($responseData['post']['id'])->toBe($post->id);
    });

    it('requires authentication', function () {
        Sanctum::actingAs(User::factory()->create(), []); // No create_posts permission

        $this->postJson('/api/posts', ['content' => 'Test content'])
            ->assertStatus(403); // Forbidden due to missing permission

        expect(Post::count())->toBe(0);
    });
});

describe('Moderation Job Tests', function () {
    it('processes approved content successfully', function () {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                GeminiModerationService::fakeAnswers('approved'),
                200
            ),
        ]);

        $post = Post::factory()->create(['status' => PostStatus::Pending]);
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);

        $job->handle($service);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Approved)
            ->and($post->moderation_reason)->toBeNull();

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation)->not->toBeNull()
            ->and($moderation->approved)->toBeTrue()
            ->and($moderation->severity)->toBe(PostModerationSeverity::Low)
            ->and($moderation->confidence)->toBe(0.95)
            ->and($moderation->reason)->toBe('Content is safe')
            ->and($moderation->error)->toBeFalse();
    });

    it('processes rejected content successfully', function () {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                GeminiModerationService::fakeAnswers('rejected'),
                200
            ),
        ]);

        $post = Post::factory()->create(['status' => PostStatus::Pending]);
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);

        $job->handle($service);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Rejected)
            ->and($post->moderation_reason)->toBe('Content violates community standards');

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation)->not->toBeNull()
            ->and($moderation->approved)->toBeFalse()
            ->and($moderation->severity)->toBe(PostModerationSeverity::High)
            ->and($moderation->confidence)->toBe(0.99)
            ->and($moderation->categories)->toEqual(['spam', 'hate_speech'])
            ->and($moderation->reason)->toBe('Content violates community standards')
            ->and($moderation->error)->toBeFalse();
    });

    it('handles API errors gracefully', function () {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $post = Post::factory()->create(['status' => PostStatus::Pending]);
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);

        try {
            $job->handle($service);
        } catch (Exception $e) {
            expect($e->getMessage())->toContain('Service unavailable');
        }

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Pending);
        expect(PostModeration::where('post_id', $post->id)->count())->toBe(0);
    });

    it('creates failed moderation record on final attempt', function () {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $post = Post::factory()->create(['status' => PostStatus::Pending]);
        $service = new GeminiModerationService;

        // Create a mock job that simulates being on final attempt
        $job = new class($post->id) extends ModeratePostContentJob
        {
            public function attempts(): int
            {
                return $this->tries; // Simulate max attempts reached
            }
        };

        $job->handle($service);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Rejected)
            ->and($post->moderation_reason)->toBe('Content could not be moderated due to service issues');

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation)->not->toBeNull()
            ->and($moderation->approved)->toBeFalse()
            ->and($moderation->error)->toBeTrue()
            ->and($moderation->reason)->toContain('Moderation service failed');
    });

    it('handles invalid JSON response', function () {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                GeminiModerationService::fakeAnswers('invalid_json'),
                200
            ),
        ]);

        $post = Post::factory()->create(['status' => PostStatus::Pending]);
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);

        try {
            $job->handle($service);
        } catch (Exception $e) {
            // The service catches JSON errors and converts to "Service unavailable"
            expect($e->getMessage())->toContain('Service unavailable');
        }

        expect(PostModeration::where('post_id', $post->id)->count())->toBe(0);
    });

    it('handles empty API response', function () {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                GeminiModerationService::fakeAnswers('empty_response'),
                200
            ),
        ]);

        $post = Post::factory()->create(['status' => PostStatus::Pending]);
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);

        try {
            $job->handle($service);
        } catch (Exception $e) {
            // The service catches empty response errors and converts to "Service unavailable"
            expect($e->getMessage())->toContain('Service unavailable');
        }

        expect(PostModeration::where('post_id', $post->id)->count())->toBe(0);
    });

    it('handles missing post gracefully', function () {
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob(99999);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $job->handle($service);
    });
});

describe('End-to-End Flow Tests', function () {
    it('completes full approved post flow', function () {
        Queue::fake();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                GeminiModerationService::fakeAnswers('approved'),
                200
            ),
        ]);

        $content = 'This is appropriate content that should be approved';

        $response = $this->postJson('/api/posts', ['content' => $content])
            ->assertStatus(201);

        $post = Post::find($response->json('post.id'));
        expect($post->status)->toBe(PostStatus::Pending);

        // Process moderation
        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);
        $job->handle($service);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Approved)
            ->and($post->moderation_reason)->toBeNull();

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation->approved)->toBeTrue()
            ->and($moderation->error)->toBeFalse();
    });

    it('completes full rejected post flow', function () {
        Queue::fake();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                GeminiModerationService::fakeAnswers('rejected'),
                200
            ),
        ]);

        $content = 'This is spam content that should be rejected';

        $response = $this->postJson('/api/posts', ['content' => $content])
            ->assertStatus(201);

        $post = Post::find($response->json('post.id'));
        expect($post->status)->toBe(PostStatus::Pending);

        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);
        $job->handle($service);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Rejected)
            ->and($post->moderation_reason)->toBe('Content violates community standards');

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation->approved)->toBeFalse()
            ->and($moderation->error)->toBeFalse()
            ->and($moderation->categories)->toContain('spam');
    });

    it('handles service failures gracefully', function () {
        $post = Post::factory()->create(['status' => PostStatus::Pending]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $service = new GeminiModerationService;

        $job = new class($post->id) extends ModeratePostContentJob
        {
            public function attempts(): int
            {
                return $this->tries;
            }
        };

        $job->handle($service);

        $post->refresh();
        expect($post->status)->toBe(PostStatus::Rejected)
            ->and($post->moderation_reason)->toBe('Content could not be moderated due to service issues');

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation->approved)->toBeFalse()
            ->and($moderation->error)->toBeTrue();
    });

    it('validates post creation with default pending status', function () {
        Queue::fake();
        $content = 'Test post content';

        $response = $this->postJson('/api/posts', ['content' => $content])
            ->assertStatus(201);

        $post = Post::find($response->json('post.id'));

        expect($post->status)->toBe(PostStatus::Pending)
            ->and($post->moderation_reason)->toBeNull()
            ->and($post->content)->toBe($content);
    });

    it('ensures moderation creates proper records', function () {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(GeminiModerationService::fakeAnswers('approved'), 200),
        ]);
        $post = Post::factory()->create(['content' => 'Test moderation content']);

        expect(PostModeration::where('post_id', $post->id)->count())->toBe(0);

        $service = new GeminiModerationService;
        $job = new ModeratePostContentJob($post->id);
        $job->handle($service);

        expect(PostModeration::where('post_id', $post->id)->count())->toBe(1);

        $moderation = PostModeration::where('post_id', $post->id)->first();
        expect($moderation->post_id)->toBe($post->id)
            ->and($moderation->approved)->toBeTrue()
            ->and($moderation->error)->toBeFalse();
    });
});
