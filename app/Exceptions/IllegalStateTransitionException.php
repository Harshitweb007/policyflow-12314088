<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PolicyState;
use RuntimeException;

final class IllegalStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly PolicyState $from,
        public readonly PolicyState $to,
    ) {
        parent::__construct("Illegal policy state transition: {$from->value} → {$to->value}");
    }
}
