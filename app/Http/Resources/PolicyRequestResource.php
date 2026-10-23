<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PolicyRequest;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PolicyRequest */
class PolicyRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $includeHistory = (bool) ($this->additional['include_history'] ?? true);

        return [
            'id' => $this->uuid,
            'agent_id' => $this->agent_id,
            'client_request_id' => $this->client_request_id,
            'state' => $this->state->value,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'destination' => $this->destination->value,
            'trip_start' => $this->trip_start?->toDateString(),
            'trip_end' => $this->trip_end?->toDateString(),
            'plan_code' => $this->plan_code->value,
            'sum_insured' => $this->sum_insured,
            'premium' => [
                'amount' => $this->asMoney($this->premium_amount),
                'currency' => $this->currency,
                'base_daily_rate' => $this->asMoney($this->base_daily_rate),
                'travel_days' => $this->travel_days,
                'customer_age' => $this->customer_age,
                'age_band' => $this->age_band,
                'age_multiplier' => $this->asMoney($this->age_multiplier),
                'destination_multiplier' => $this->asMoney($this->destination_multiplier),
                'coverage_multiplier' => $this->asMoney($this->coverage_multiplier),
            ],
            'provider_reference' => $this->provider_reference,
            'policy_number' => $this->policy_number,
            'attempt_count' => $this->attempt_count,
            'last_error' => $this->last_error_code === null && $this->last_error_message === null
                ? null
                : [
                    'code' => $this->last_error_code,
                    'message' => $this->last_error_message,
                ],
            'last_provider_summary' => $this->last_provider_summary,
            'state_history' => $includeHistory
                ? $this->stateHistory->map(static fn ($row) => [
                    'from' => $row->from_state?->value,
                    'to' => $row->to_state->value,
                    'reason' => $row->reason,
                    'at' => $row->created_at?->toIso8601String(),
                    'issuance_attempt_id' => $row->issuance_attempt_id,
                    'provider_event_id' => $row->provider_event_id,
                ])->values()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function asMoney(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }
}
