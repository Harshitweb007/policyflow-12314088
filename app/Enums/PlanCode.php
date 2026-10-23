<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanCode: string
{
    case Basic = 'BASIC';
    case Plus = 'PLUS';
    case Premium = 'PREMIUM';

    public function baseDailyRate(): string
    {
        return match ($this) {
            self::Basic => '12.00',
            self::Plus => '20.00',
            self::Premium => '30.00',
        };
    }
}
