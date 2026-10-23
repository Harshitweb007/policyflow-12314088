<?php

declare(strict_types=1);

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case TimedOut = 'TIMED_OUT';
    case Accepted = 'ACCEPTED';
}
