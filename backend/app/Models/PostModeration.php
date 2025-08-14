<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostModerationSeverity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostModeration extends Model
{
    /** @use HasFactory<\Database\Factories\PostModerationFactory> */
    use HasFactory;

    protected $fillable = [
        'post_id',
        'approved',
        'categories',
        'severity',
        'confidence',
        'reason',
        'error',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'categories' => 'array',
        'severity' => PostModerationSeverity::class,
        'confidence' => 'float',
        'error' => 'boolean',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
