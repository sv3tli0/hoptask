<?php

declare(strict_types=1);

namespace App\Enums;

enum PostModerationSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    const PostModerationSeverity DEFAULT = self::Medium;
}
