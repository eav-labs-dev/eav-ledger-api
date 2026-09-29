<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
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
            'catalog_number' => 'ITEM-'.fake()->unique()->numerify('##########'),
            'type' => fake()->randomElement(['product', 'service']),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'unit_price' => fake()->randomFloat(2, 1, 10000),
            'currency' => 'GHS',
            'status' => 'active',
        ];
    }
}
