<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create([
                'owner_id' => $attributes['owner_id'],
            ])->id,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('##########'),
            'status' => 'draft',
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'currency' => 'GHS',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'notes' => null,
        ];
    }
}
