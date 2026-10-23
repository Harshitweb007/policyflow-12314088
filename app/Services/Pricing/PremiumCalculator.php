<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\AgeBand;
use App\Enums\CoverageAmount;
use App\Enums\Destination;
use App\Enums\PlanCode;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class PremiumCalculator
{
    /**
     * Commercial rounding: nearest neighbour, ties away from zero (schoolbook HALF_UP).
     */
    public const ROUNDING_MODE = RoundingMode::HalfUp;

    public function calculate(
        PlanCode $plan,
        Destination $destination,
        int $sumInsured,
        string $dateOfBirth,
        string $tripStart,
        string $tripEnd,
        ?CarbonImmutable $today = null,
    ): PremiumSnapshot {
        $today ??= CarbonImmutable::now()->startOfDay();
        $dob = $this->parseDate($dateOfBirth, 'date_of_birth');
        $start = $this->parseDate($tripStart, 'trip_start');
        $end = $this->parseDate($tripEnd, 'trip_end');

        if ($end->lt($start)) {
            throw new InvalidArgumentException('trip_end must be greater than or equal to trip_start.');
        }

        if ($dob->gt($today)) {
            throw new InvalidArgumentException('date_of_birth cannot be in the future.');
        }

        if ($dob->gt($start)) {
            throw new InvalidArgumentException('date_of_birth cannot be after trip_start.');
        }

        if ($dob->year < 1900) {
            throw new InvalidArgumentException('date_of_birth is not a plausible date.');
        }

        $coverage = CoverageAmount::tryFrom($sumInsured);
        if ($coverage === null) {
            throw new InvalidArgumentException('Unsupported sum_insured value.');
        }

        $travelDays = $start->diff($end)->days + 1;
        if ($travelDays < 1) {
            throw new InvalidArgumentException('Travel duration must be at least one day.');
        }

        $age = $dob->diff($start)->y;
        $ageBand = AgeBand::fromAge($age);

        $base = BigDecimal::of($plan->baseDailyRate());
        $ageMultiplier = BigDecimal::of($ageBand->multiplier());
        $destinationMultiplier = BigDecimal::of($destination->multiplier());
        $coverageMultiplier = BigDecimal::of($coverage->multiplier());

        $premium = $base
            ->multipliedBy($travelDays)
            ->multipliedBy($ageMultiplier)
            ->multipliedBy($destinationMultiplier)
            ->multipliedBy($coverageMultiplier)
            ->toScale(2, self::ROUNDING_MODE);

        return new PremiumSnapshot(
            baseDailyRate: $plan->baseDailyRate(),
            travelDays: $travelDays,
            customerAge: $age,
            ageBand: $ageBand,
            ageMultiplier: $ageBand->multiplier(),
            destinationMultiplier: $destination->multiplier(),
            coverageMultiplier: $coverage->multiplier(),
            premium: (string) $premium,
            currency: 'INR',
        );
    }

    private function parseDate(string $value, string $field): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            throw new InvalidArgumentException("Invalid {$field} date.");
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Invalid {$field} date.");
        }

        return $date->startOfDay();
    }
}
