<?php

namespace App\Enums;

enum PostStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    const DEFAULT = self::Pending;
}
