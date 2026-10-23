<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CoverageAmount;
use App\Enums\Destination;
use App\Enums\PlanCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:64'],
            'agent_id' => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'destination' => ['required', 'string', Rule::enum(Destination::class)],
            'trip_start' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_of_birth'],
            'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', Rule::enum(PlanCode::class)],
            'sum_insured' => ['required', 'integer', Rule::in(CoverageAmount::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_of_birth.after' => 'date_of_birth must be a plausible date on or after 1900-01-01.',
            'date_of_birth.before_or_equal' => 'date_of_birth cannot be in the future.',
            'sum_insured.in' => 'Unsupported coverage amount. Allowed: 100000, 300000, 500000.',
            'plan_code.Illuminate\Validation\Rules\Enum' => 'Unsupported plan_code. Allowed: BASIC, PLUS, PREMIUM.',
            'destination.Illuminate\Validation\Rules\Enum' => 'Unsupported destination. Allowed: DOMESTIC, INTERNATIONAL.',
        ];
    }
}
