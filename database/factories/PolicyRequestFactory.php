<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Destination;
use App\Enums\MockScenario;
use App\Enums\PlanCode;
use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Services\Pricing\PremiumCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PolicyRequest>
 */
class PolicyRequestFactory extends Factory
{
    protected $model = PolicyRequest::class;

    public function definition(): array
    {
        $tripStart = '2026-11-10';
        $tripEnd = '2026-11-14';
        $dateOfBirth = '1998-04-10';
        $plan = PlanCode::Plus;
        $destination = Destination::International;
        $sumInsured = 300000;

        $snapshot = app(PremiumCalculator::class)->calculate(
            $plan,
            $destination,
            $sumInsured,
            $dateOfBirth,
            $tripStart,
            $tripEnd,
        );

        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'agent_id' => 'agent-'.$this->faker->unique()->numerify('####'),
            'client_request_id' => (string) Str::uuid(),
            'customer_name' => $this->faker->name(),
            'date_of_birth' => $dateOfBirth,
            'destination' => $destination,
            'trip_start' => $tripStart,
            'trip_end' => $tripEnd,
            'plan_code' => $plan,
            'sum_insured' => $sumInsured,
            'base_daily_rate' => $snapshot->baseDailyRate,
            'travel_days' => $snapshot->travelDays,
            'customer_age' => $snapshot->customerAge,
            'age_band' => $snapshot->ageBand->value,
            'age_multiplier' => $snapshot->ageMultiplier,
            'destination_multiplier' => $snapshot->destinationMultiplier,
            'coverage_multiplier' => $snapshot->coverageMultiplier,
            'premium_amount' => $snapshot->premium,
            'currency' => $snapshot->currency,
            'state' => PolicyState::Validated,
            'attempt_count' => 0,
            'mock_scenario' => MockScenario::Success,
            'issuance_idempotency_key' => 'issue:'.$uuid,
        ];
    }

    public function scenario(MockScenario $scenario): static
    {
        return $this->state(fn () => ['mock_scenario' => $scenario]);
    }
}
