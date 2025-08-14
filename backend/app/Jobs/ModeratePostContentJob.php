<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ModeratePostContentJob implements ShouldQueue
{
    use Queueable;

    public $queue = 'moderation';

    public function __construct(
        public private(set) readonly int $postId,
    ) {}

    public function handle(): void
    {
    }
}
