<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PolicyState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPolicyRequestsRequest extends FormRequest
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
            'state' => ['sometimes', 'string', Rule::enum(PolicyState::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('policyflow.pagination.max_per_page', 100)],
        ];
    }
}
