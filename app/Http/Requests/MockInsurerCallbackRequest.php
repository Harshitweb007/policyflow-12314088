<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProviderEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MockInsurerCallbackRequest extends FormRequest
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
            'provider_event_id' => ['required', 'string', 'max:80'],
            'provider_reference' => ['nullable', 'string', 'max:64'],
            'policy_request_id' => ['nullable', 'string', 'max:64'],
            'event_type' => ['required', 'string', Rule::enum(ProviderEventType::class)],
            'policy_number' => ['required_if:event_type,ISSUED', 'nullable', 'string', 'max:64'],
            'error_code' => ['nullable', 'string', 'max:64'],
            'error_message' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
