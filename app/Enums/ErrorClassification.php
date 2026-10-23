<?php

declare(strict_types=1);

namespace App\Enums;

enum ErrorClassification: string
{
    case None = 'NONE';
    case Retryable = 'RETRYABLE';
    case Definitive = 'DEFINITIVE';
    case Unknown = 'UNKNOWN';
}
