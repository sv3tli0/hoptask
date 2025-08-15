<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $content
 * @property PostStatus $status
 * @property string|null $moderation_reason
 */
#[ObservedBy(PostObserver::class)]
class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'content',
        'status',
        'moderation_reason',
    ];

    public $timestamps = true;

    protected $casts = [
        'status' => PostStatus::class,
    ];

    protected $appends = [
        'title',
    ];

    public function moderations(): HasMany
    {
        return $this->hasMany(PostModeration::class);
    }

    public function getTitleAttribute(): string
    {
        return 'Post '.$this->id;
    }

    public function toWebSocketArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status->value,
            'moderation_reason' => $this->moderation_reason,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
