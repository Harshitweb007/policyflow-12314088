<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderEventType: string
{
    case Issued = 'ISSUED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';
    case Accepted = 'ACCEPTED';
}
