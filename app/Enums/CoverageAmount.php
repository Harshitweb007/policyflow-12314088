<?php

declare(strict_types=1);

namespace App\Enums;

enum CoverageAmount: int
{
    case OneLakh = 100000;
    case ThreeLakh = 300000;
    case FiveLakh = 500000;

    public function multiplier(): string
    {
        return match ($this) {
            self::OneLakh => '1.00',
            self::ThreeLakh => '1.25',
            self::FiveLakh => '1.50',
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $amount) => $amount->value, self::cases());
    }
}
