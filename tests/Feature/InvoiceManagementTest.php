<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_draft_invoice_with_deterministic_totals(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $customer = Customer::factory()->create(['owner_id' => $user->id]);
        $consulting = CatalogItem::factory()->create([
            'owner_id' => $user->id,
            'unit_price' => 100,
            'currency' => 'GHS',
        ]);
        $support = CatalogItem::factory()->create([
            'owner_id' => $user->id,
            'unit_price' => 47,
            'currency' => 'GHS',
        ]);

        $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => '2026-09-28',
            'due_date' => '2026-10-28',
            'currency' => 'ghs',
            'notes' => 'Net 30',
            'lines' => [
                [
                    'catalog_item_id' => $consulting->id,
                    'quantity' => 2,
                    'tax_rate' => 15,
                ],
                [
                    'catalog_item_id' => $support->id,
                    'description' => 'Priority support',
                    'quantity' => 3,
                    'tax_rate' => 5,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'INVOICE_CREATED')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.currency', 'GHS')
            ->assertJsonPath('data.subtotal', '341.00')
            ->assertJsonPath('data.tax_total', '37.05')
            ->assertJsonPath('data.total', '378.05')
            ->assertJsonPath('data.lines.1.description', 'Priority support')
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseHas('invoices', [
            'owner_id' => $user->id,
            'customer_id' => $customer->id,
            'total' => 378.05,
        ]);
    }

    public function test_invoice_rejects_catalog_items_not_active_and_owned_by_user(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $customer = Customer::factory()->create(['owner_id' => $user->id]);
        $foreignItem = CatalogItem::factory()->create();

        $this->postJson('/api/v1/invoices', $this->payload($customer, $foreignItem))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['lines']]);

        $inactiveItem = CatalogItem::factory()->create([
            'owner_id' => $user->id,
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/invoices', $this->payload($customer, $inactiveItem))
            ->assertUnprocessable();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_invoice_list_is_paginated_filterable_and_owner_scoped(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $customer = Customer::factory()->create(['owner_id' => $user->id, 'name' => 'Acme Ghana']);
        Invoice::factory()->count(3)->create([
            'owner_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);
        Invoice::factory()->create(['owner_id' => $user->id, 'customer_id' => $customer->id, 'status' => 'issued']);
        Invoice::factory()->create();

        $this->getJson('/api/v1/invoices?search=Acme&status=draft&per_page=2&sort_by=due_date&sort_dir=asc')
            ->assertOk()
            ->assertJsonPath('code', 'INVOICES_RETRIEVED')
            ->assertJsonPath('page.total', 3)
            ->assertJsonPath('page.per_page', 2)
            ->assertJsonPath('sort.by', 'due_date')
            ->assertJsonPath('filters.status', 'draft')
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_and_delete_their_draft_invoice(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = Invoice::factory()->create(['owner_id' => $user->id]);

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id);

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('code', 'INVOICE_DELETED');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_non_draft_invoice_cannot_be_deleted(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = Invoice::factory()->create(['owner_id' => $user->id, 'status' => 'issued']);

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'INVOICE_NOT_DELETABLE');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_invoices_cannot_be_accessed_by_another_owner(): void
    {
        $invoice = Invoice::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
        $this->deleteJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();
    }

    public function test_customer_referenced_by_an_invoice_cannot_be_deleted(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $customer = Customer::factory()->create(['owner_id' => $user->id]);
        Invoice::factory()->create(['owner_id' => $user->id, 'customer_id' => $customer->id]);

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'CUSTOMER_HAS_INVOICES');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_invoice_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/invoices')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        $this->postJson('/api/v1/invoices', [])->assertUnauthorized();
    }

    /** @return array<string, mixed> */
    private function payload(Customer $customer, CatalogItem $item): array
    {
        return [
            'customer_id' => $customer->id,
            'issue_date' => '2026-09-28',
            'due_date' => '2026-10-28',
            'currency' => $item->currency,
            'lines' => [[
                'catalog_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ];
    }
}
