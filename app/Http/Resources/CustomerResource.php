<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_number' => $this->customer_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'billing_address' => [
                'line_1' => $this->address_line_1,
                'line_2' => $this->address_line_2,
                'city' => $this->city,
                'region' => $this->region,
                'postal_code' => $this->postal_code,
                'country_code' => $this->country_code,
            ],
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
