<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogItemRequest extends FormRequest
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
            'type' => ['sometimes', 'required', 'in:product,service'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                Rule::unique('catalog_items', 'sku')
                    ->where('owner_id', $this->user()->id)
                    ->ignore((int) $this->route('catalog_item')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('sku')) {
            $this->merge([
                'sku' => $this->filled('sku') ? strtoupper(trim((string) $this->input('sku'))) : null,
            ]);
        }

        if ($this->exists('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }
}
