<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\Destination;
use App\Enums\ErrorClassification;
use App\Enums\MockScenario;
use App\Enums\PlanCode;
use App\Enums\PolicyState;
use Database\Factories\PolicyRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PolicyRequest extends Model
{
    /** @use HasFactory<PolicyRequestFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'trip_start' => 'date',
            'trip_end' => 'date',
            'destination' => Destination::class,
            'plan_code' => PlanCode::class,
            'state' => PolicyState::class,
            'mock_scenario' => MockScenario::class,
            'sum_insured' => 'integer',
            'travel_days' => 'integer',
            'customer_age' => 'integer',
            'attempt_count' => 'integer',
            'base_daily_rate' => 'decimal:2',
            'age_multiplier' => 'decimal:2',
            'destination_multiplier' => 'decimal:2',
            'coverage_multiplier' => 'decimal:2',
            'premium_amount' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  Builder<PolicyRequest>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Builder<PolicyRequest>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? 'uuid', $value)
            ->when($field === null && is_numeric($value), fn ($q) => $q->orWhere('id', (int) $value));
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class);
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('id');
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class);
    }

    public function latestInProgressAttempt(): ?IssuanceAttempt
    {
        return $this->attempts()
            ->where('status', AttemptStatus::InProgress->value)
            ->latest('id')
            ->first();
    }

    public function lastAttemptIsRetryableFailure(): bool
    {
        $last = $this->attempts()->latest('id')->first();

        if ($last === null) {
            return false;
        }

        return $last->error_classification === ErrorClassification::Retryable;
    }

    public function staleAttemptDeadline(): Carbon
    {
        return now()->subSeconds((int) config('policyflow.stale_attempt_seconds'));
    }
}
