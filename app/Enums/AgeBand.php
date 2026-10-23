<?php

declare(strict_types=1);

namespace App\Enums;

enum AgeBand: string
{
    case Child = 'CHILD';
    case Adult = 'ADULT';
    case Senior = 'SENIOR';
    case SuperSenior = 'SUPER_SENIOR';

    public function multiplier(): string
    {
        return match ($this) {
            self::Child => '1.10',
            self::Adult => '1.00',
            self::Senior => '1.50',
            self::SuperSenior => '2.00',
        };
    }

    public static function fromAge(int $age): self
    {
        return match (true) {
            $age <= 17 => self::Child,
            $age <= 60 => self::Adult,
            $age <= 70 => self::Senior,
            default => self::SuperSenior,
        };
    }
}
