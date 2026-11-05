<?php

namespace App\Http\Requests;

use App\Enums\OutcomeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutcomeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'string', Rule::exists('properties', 'id')],
            'recorded_at' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(OutcomeStatus::class)],
            'noticed_value' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'final_value' => [
                Rule::requiredIf(fn () => $this->input('status') === OutcomeStatus::Final->value),
                Rule::prohibitedIf(fn () => $this->input('status') === OutcomeStatus::Pending->value),
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'final_value.required' => 'A final outcome needs a final value.',
            'final_value.prohibited' => 'A pending outcome cannot have a final value yet.',
            'property_id.exists' => 'Choose a property that has been imported.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Treat a blank final value as "none" so pending outcomes validate cleanly.
        if ($this->input('final_value') === '') {
            $this->merge(['final_value' => null]);
        }
    }
}
