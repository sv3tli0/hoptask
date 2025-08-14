<?php

declare(strict_types=1);

namespace App\Enums;

enum PostStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    const PostStatus DEFAULT = self::Pending;
}
