<?php

declare(strict_types=1);

namespace App\Enums;

enum Destination: string
{
    case Domestic = 'DOMESTIC';
    case International = 'INTERNATIONAL';

    public function multiplier(): string
    {
        return match ($this) {
            self::Domestic => '1.00',
            self::International => '1.80',
        };
    }
}
