<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_products_and_services(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/v1/catalog-items', [
            'type' => 'service',
            'sku' => 'consult-01',
            'name' => 'Technical Consulting',
            'unit_price' => 2500,
            'currency' => 'ghs',
        ])
            ->assertCreated()
            ->assertJsonPath('code', 'CATALOG_ITEM_CREATED')
            ->assertJsonPath('data.type', 'service')
            ->assertJsonPath('data.sku', 'CONSULT-01')
            ->assertJsonPath('data.unit_price', '2500.00')
            ->assertJsonPath('data.currency', 'GHS');

        $this->assertDatabaseHas('catalog_items', [
            'owner_id' => $user->id,
            'sku' => 'CONSULT-01',
        ]);
    }

    public function test_sku_is_unique_per_owner_but_reusable_by_another_owner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        CatalogItem::factory()->create(['owner_id' => $first->id, 'sku' => 'SKU-001']);

        Sanctum::actingAs($first);
        $this->postJson('/api/v1/catalog-items', $this->payload('SKU-001'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        Sanctum::actingAs($second);
        $this->postJson('/api/v1/catalog-items', $this->payload('SKU-001'))
            ->assertCreated();
    }

    public function test_catalog_list_is_filterable_paginated_and_owner_scoped(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        CatalogItem::factory()->count(3)->create([
            'owner_id' => $user->id,
            'type' => 'service',
            'status' => 'active',
        ]);
        CatalogItem::factory()->create(['owner_id' => $user->id, 'type' => 'product']);
        CatalogItem::factory()->create();

        $this->getJson('/api/v1/catalog-items?type=service&status=active&per_page=2&sort_by=unit_price&sort_dir=asc')
            ->assertOk()
            ->assertJsonPath('page.total', 3)
            ->assertJsonPath('page.per_page', 2)
            ->assertJsonPath('sort.by', 'unit_price')
            ->assertJsonPath('filters.type', 'service')
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_update_and_delete_their_item(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $item = CatalogItem::factory()->create(['owner_id' => $user->id]);

        $this->getJson("/api/v1/catalog-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $item->id);

        $this->patchJson("/api/v1/catalog-items/{$item->id}", [
            'name' => 'Updated offering',
            'unit_price' => 99.5,
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.unit_price', '99.50')
            ->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/v1/catalog-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('code', 'CATALOG_ITEM_DELETED');

        $this->assertDatabaseMissing('catalog_items', ['id' => $item->id]);
    }

    public function test_catalog_items_cannot_be_accessed_by_another_owner(): void
    {
        $item = CatalogItem::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/catalog-items/{$item->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->patchJson("/api/v1/catalog-items/{$item->id}", ['name' => 'Stolen'])
            ->assertNotFound();

        $this->deleteJson("/api/v1/catalog-items/{$item->id}")
            ->assertNotFound();
    }

    public function test_catalog_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/catalog-items')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');
    }

    /** @return array<string, mixed> */
    private function payload(string $sku): array
    {
        return [
            'type' => 'product',
            'sku' => $sku,
            'name' => 'Ledger product',
            'unit_price' => 100,
            'currency' => 'GHS',
        ];
    }
}
