<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\AgeBand;

final readonly class PremiumSnapshot
{
    public function __construct(
        public string $baseDailyRate,
        public int $travelDays,
        public int $customerAge,
        public AgeBand $ageBand,
        public string $ageMultiplier,
        public string $destinationMultiplier,
        public string $coverageMultiplier,
        public string $premium,
        public string $currency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPersistenceArray(): array
    {
        return [
            'base_daily_rate' => $this->baseDailyRate,
            'travel_days' => $this->travelDays,
            'customer_age' => $this->customerAge,
            'age_band' => $this->ageBand->value,
            'age_multiplier' => $this->ageMultiplier,
            'destination_multiplier' => $this->destinationMultiplier,
            'coverage_multiplier' => $this->coverageMultiplier,
            'premium_amount' => $this->premium,
            'currency' => $this->currency,
        ];
    }
}
