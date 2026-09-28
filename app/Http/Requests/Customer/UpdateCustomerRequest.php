<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'region' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country_code' => ['sometimes', 'required', 'string', 'size:2'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('email')) {
            $this->merge([
                'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            ]);
        }

        if ($this->exists('country_code')) {
            $this->merge(['country_code' => strtoupper((string) $this->input('country_code'))]);
        }
    }
}
