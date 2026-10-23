<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AgeBand;
use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Services\Pricing\PremiumCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PremiumCalculatorTest extends TestCase
{
    private PremiumCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PremiumCalculator;
    }

    public function test_documented_acceptance_example_is_225(): void
    {
        $snapshot = $this->calculator->calculate(
            PlanCode::Plus,
            Destination::International,
            300000,
            '1998-04-10',
            '2026-11-10',
            '2026-11-14',
            CarbonImmutable::parse('2026-10-23'),
        );

        $this->assertSame(5, $snapshot->travelDays);
        $this->assertSame('1.00', $snapshot->ageMultiplier);
        $this->assertSame('1.80', $snapshot->destinationMultiplier);
        $this->assertSame('1.25', $snapshot->coverageMultiplier);
        $this->assertSame('225.00', $snapshot->premium);
        $this->assertSame('INR', $snapshot->currency);
        $this->assertSame(AgeBand::Adult, $snapshot->ageBand);
    }

    /**
     * @dataProvider plans
     */
    #[DataProvider('plans')]
    public function test_each_plan_base_rate(PlanCode $plan, string $expectedBase, string $expectedPremium): void
    {
        $snapshot = $this->calculator->calculate(
            $plan,
            Destination::Domestic,
            100000,
            '1990-01-01',
            '2026-11-10',
            '2026-11-10',
        );

        $this->assertSame($expectedBase, $snapshot->baseDailyRate);
        $this->assertSame($expectedPremium, $snapshot->premium);
        $this->assertSame(1, $snapshot->travelDays);
    }

    /**
     * @return array<string, array{0: PlanCode, 1: string, 2: string}>
     */
    public static function plans(): array
    {
        return [
            'BASIC' => [PlanCode::Basic, '12.00', '12.00'],
            'PLUS' => [PlanCode::Plus, '20.00', '20.00'],
            'PREMIUM' => [PlanCode::Premium, '30.00', '30.00'],
        ];
    }

    /**
     * @dataProvider ageBands
     */
    #[DataProvider('ageBands')]
    public function test_age_bands(string $dob, string $tripStart, string $multiplier, AgeBand $band): void
    {
        $snapshot = $this->calculator->calculate(
            PlanCode::Basic,
            Destination::Domestic,
            100000,
            $dob,
            $tripStart,
            $tripStart,
            CarbonImmutable::parse('2026-12-01'),
        );

        $this->assertSame($multiplier, $snapshot->ageMultiplier);
        $this->assertSame($band, $snapshot->ageBand);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: AgeBand}>
     */
    public static function ageBands(): array
    {
        return [
            'child upper bound 17' => ['2009-11-10', '2026-11-10', '1.10', AgeBand::Child],
            'adult lower bound 18' => ['2008-11-10', '2026-11-10', '1.00', AgeBand::Adult],
            'adult upper bound 60' => ['1966-11-10', '2026-11-10', '1.00', AgeBand::Adult],
            'senior lower bound 61' => ['1965-11-10', '2026-11-10', '1.50', AgeBand::Senior],
            'senior upper bound 70' => ['1956-11-10', '2026-11-10', '1.50', AgeBand::Senior],
            'super senior 71' => ['1955-11-10', '2026-11-10', '2.00', AgeBand::SuperSenior],
        ];
    }

    public function test_birthday_boundary_is_inclusive_on_the_birthday(): void
    {
        $dayBefore = $this->calculator->calculate(
            PlanCode::Basic,
            Destination::Domestic,
            100000,
            '2008-11-11',
            '2026-11-10',
            '2026-11-10',
        );
        $onBirthday = $this->calculator->calculate(
            PlanCode::Basic,
            Destination::Domestic,
            100000,
            '2008-11-10',
            '2026-11-10',
            '2026-11-10',
        );

        $this->assertSame(17, $dayBefore->customerAge);
        $this->assertSame('1.10', $dayBefore->ageMultiplier);
        $this->assertSame(18, $onBirthday->customerAge);
        $this->assertSame('1.00', $onBirthday->ageMultiplier);
    }

    public function test_leap_year_birthday(): void
    {
        $before = $this->calculator->calculate(
            PlanCode::Basic,
            Destination::Domestic,
            100000,
            '2004-02-29',
            '2023-02-28',
            '2023-02-28',
            CarbonImmutable::parse('2026-01-01'),
        );
        $onAnniversary = $this->calculator->calculate(
            PlanCode::Basic,
            Destination::Domestic,
            100000,
            '2004-02-29',
            '2024-02-29',
            '2024-02-29',
            CarbonImmutable::parse('2026-01-01'),
        );

        $this->assertSame(18, $before->customerAge);
        $this->assertSame(20, $onAnniversary->customerAge);
    }

    public function test_destinations_and_coverage(): void
    {
        $domestic = $this->calculator->calculate(PlanCode::Plus, Destination::Domestic, 100000, '1990-01-01', '2026-11-10', '2026-11-14');
        $international = $this->calculator->calculate(PlanCode::Plus, Destination::International, 100000, '1990-01-01', '2026-11-10', '2026-11-14');
        $threeLakh = $this->calculator->calculate(PlanCode::Plus, Destination::Domestic, 300000, '1990-01-01', '2026-11-10', '2026-11-14');
        $fiveLakh = $this->calculator->calculate(PlanCode::Plus, Destination::Domestic, 500000, '1990-01-01', '2026-11-10', '2026-11-14');

        $this->assertSame('1.00', $domestic->destinationMultiplier);
        $this->assertSame('100.00', $domestic->premium);
        $this->assertSame('1.80', $international->destinationMultiplier);
        $this->assertSame('180.00', $international->premium);
        $this->assertSame('1.25', $threeLakh->coverageMultiplier);
        $this->assertSame('125.00', $threeLakh->premium);
        $this->assertSame('1.50', $fiveLakh->coverageMultiplier);
        $this->assertSame('150.00', $fiveLakh->premium);
    }

    public function test_same_day_trip_is_one_travel_day(): void
    {
        $snapshot = $this->calculator->calculate(PlanCode::Plus, Destination::Domestic, 100000, '1990-01-01', '2026-11-10', '2026-11-10');
        $this->assertSame(1, $snapshot->travelDays);
        $this->assertSame('20.00', $snapshot->premium);
    }

    public function test_half_up_rounding(): void
    {
        $snapshot = $this->calculator->calculate(
            PlanCode::Basic,
            Destination::International,
            100000,
            '2016-11-10',
            '2026-11-10',
            '2026-11-10',
        );

        $this->assertSame('1.10', $snapshot->ageMultiplier);
        $this->assertSame('23.76', $snapshot->premium);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(PlanCode::Plus, Destination::Domestic, 100000, '1990-01-01', '2026-11-14', '2026-11-10');
    }

    public function test_unsupported_coverage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(PlanCode::Plus, Destination::Domestic, 200000, '1990-01-01', '2026-11-10', '2026-11-10');
    }

    public function test_future_date_of_birth_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(
            PlanCode::Plus,
            Destination::Domestic,
            100000,
            '2027-01-01',
            '2027-11-10',
            '2027-11-11',
            CarbonImmutable::parse('2026-10-23'),
        );
    }

    public function test_implausible_date_of_birth_before_1900_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(
            PlanCode::Plus,
            Destination::Domestic,
            100000,
            '1899-12-31',
            '2026-11-10',
            '2026-11-11',
            CarbonImmutable::parse('2026-10-23'),
        );
    }

    public function test_date_of_birth_after_trip_start_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(
            PlanCode::Plus,
            Destination::Domestic,
            100000,
            '2026-11-12',
            '2026-11-10',
            '2026-11-14',
            CarbonImmutable::parse('2026-10-23'),
        );
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(
            PlanCode::Plus,
            Destination::Domestic,
            100000,
            'not-a-date',
            '2026-11-10',
            '2026-11-14',
            CarbonImmutable::parse('2026-10-23'),
        );
    }
}
