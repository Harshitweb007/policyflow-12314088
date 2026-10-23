<?php

declare(strict_types=1);

namespace App\Enums;

enum PolicyState: string
{
    case Received = 'RECEIVED';
    case Validated = 'VALIDATED';
    case Submitted = 'SUBMITTED';
    case PendingConfirmation = 'PENDING_CONFIRMATION';
    case Issued = 'ISSUED';
    case Failed = 'FAILED';
    case NeedsReconciliation = 'NEEDS_RECONCILIATION';

    public function isIssued(): bool
    {
        return $this === self::Issued;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $state) => $state->value, self::cases());
    }
}
