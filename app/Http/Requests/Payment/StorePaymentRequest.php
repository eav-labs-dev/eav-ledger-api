<?php

namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'method' => ['required', 'in:bank_transfer,card,cash,mobile_money,cheque'],
            'reference' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('payments', 'reference')->where('owner_id', $this->user()->id),
            ],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'method' => strtolower(trim((string) $this->input('method'))),
            'reference' => $this->filled('reference')
                ? strtoupper(trim((string) $this->input('reference')))
                : null,
        ]);
    }
}
