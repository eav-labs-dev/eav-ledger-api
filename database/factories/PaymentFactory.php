<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'invoice_id' => fn (array $attributes) => Invoice::factory()->create([
                'owner_id' => $attributes['owner_id'],
                'status' => 'issued',
            ])->id,
            'payment_number' => 'PAY-'.fake()->unique()->numerify('##########'),
            'amount' => fake()->randomFloat(2, 1, 1000),
            'method' => fake()->randomElement(['bank_transfer', 'card', 'cash', 'mobile_money']),
            'reference' => fake()->unique()->bothify('REF-########'),
            'paid_at' => now(),
            'notes' => null,
        ];
    }
}
