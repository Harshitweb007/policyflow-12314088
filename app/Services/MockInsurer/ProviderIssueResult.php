<?php

declare(strict_types=1);

namespace App\Services\MockInsurer;

final readonly class ProviderIssueResult
{
    public function __construct(
        public string $outcome,
        public ?string $providerReference,
        public ?string $policyNumber,
        public ?string $errorCode,
        public ?string $errorMessage,
        public int $httpStatus,
        public array $body,
    ) {}

    public function isSuccess(): bool
    {
        return $this->outcome === 'SUCCESS';
    }

    public function isAcceptedPending(): bool
    {
        return $this->outcome === 'ACCEPTED';
    }

    public function isRetryableFailure(): bool
    {
        return $this->outcome === 'TEMPORARY_FAILURE';
    }

    public function isUnknown(): bool
    {
        return $this->outcome === 'UNKNOWN';
    }
}
