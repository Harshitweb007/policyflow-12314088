<?php

declare(strict_types=1);

namespace App\Enums;

enum MockScenario: string
{
    case Success = 'SUCCESS';
    case TemporaryFailure = 'TEMPORARY_FAILURE';
    case TimeoutAfterIssue = 'TIMEOUT_AFTER_ISSUE';
    case DelayedCallback = 'DELAYED_CALLBACK';
}
