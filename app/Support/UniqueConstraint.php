<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;

final class UniqueConstraint
{
    public static function isViolation(QueryException $exception, ?string $constraintHint = null): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->getMessage());

        $looksUnique = $sqlState === '23505'
            || ($sqlState === '23000' && in_array((int) $driverCode, [1062, 19], true))
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'unique violation')
            || str_contains($message, 'duplicate entry');

        if (! $looksUnique) {
            return false;
        }

        if ($constraintHint === null) {
            return str_contains($message, 'unique') || str_contains($message, 'duplicate');
        }

        return str_contains($message, strtolower($constraintHint));
    }
}
