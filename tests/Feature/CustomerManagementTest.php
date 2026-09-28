<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_customer(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Acme Ghana Ltd',
            'email' => 'BILLING@ACME.GH',
            'phone' => '+233200000000',
            'address_line_1' => '1 Independence Avenue',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'country_code' => 'gh',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('code', 'CUSTOMER_CREATED')
            ->assertJsonPath('data.email', 'billing@acme.gh')
            ->assertJsonPath('data.billing_address.country_code', 'GH');

        $this->assertDatabaseHas('customers', [
            'owner_id' => $user->id,
            'email' => 'billing@acme.gh',
        ]);
    }

    public function test_customer_list_is_paginated_filterable_and_scoped_to_owner(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        Customer::factory()->count(3)->create(['owner_id' => $user->id, 'status' => 'active']);
        Customer::factory()->create(['owner_id' => $user->id, 'status' => 'inactive']);
        Customer::factory()->create();

        $this->getJson('/api/v1/customers?status=active&per_page=2&sort_by=name&sort_dir=asc')
            ->assertOk()
            ->assertJsonPath('code', 'CUSTOMERS_RETRIEVED')
            ->assertJsonPath('page.current', 1)
            ->assertJsonPath('page.per_page', 2)
            ->assertJsonPath('page.total', 3)
            ->assertJsonPath('sort.by', 'name')
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_update_and_delete_their_customer(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $customer = Customer::factory()->create(['owner_id' => $user->id]);

        $this->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);

        $this->patchJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Updated Customer',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'CUSTOMER_UPDATED')
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('code', 'CUSTOMER_DELETED');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_records_cannot_be_accessed_by_another_user(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/customers/{$customer->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->patchJson("/api/v1/customers/{$customer->id}", ['name' => 'Stolen'])
            ->assertNotFound();

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/customers')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        $this->postJson('/api/v1/customers', [])->assertUnauthorized();
    }
}
