<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_invoice_can_be_issued_and_audited(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->invoiceWithLine($user);

        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", [
            'status' => 'issued',
            'note' => 'Approved for delivery',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'INVOICE_TRANSITIONED')
            ->assertJsonPath('data.status', 'issued');

        $this->getJson("/api/v1/invoices/{$invoice->id}/history")
            ->assertOk()
            ->assertJsonPath('code', 'INVOICE_HISTORY_RETRIEVED')
            ->assertJsonPath('data.0.from_status', 'draft')
            ->assertJsonPath('data.0.to_status', 'issued')
            ->assertJsonPath('data.0.note', 'Approved for delivery')
            ->assertJsonPath('data.0.actor.id', $user->id);
    }

    public function test_draft_invoice_cannot_skip_to_a_payment_state(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->invoiceWithLine($user);

        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->assertSame('draft', $invoice->refresh()->status);
        $this->assertDatabaseCount('invoice_events', 0);
    }

    public function test_issued_invoice_can_be_voided_but_not_reversed(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->invoiceWithLine($user);

        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", ['status' => 'issued'])->assertOk();
        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", [
            'status' => 'void',
            'note' => 'Customer cancelled',
        ])->assertOk();

        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", ['status' => 'issued'])
            ->assertConflict()
            ->assertJsonPath('code', 'INVOICE_TRANSITION_NOT_ALLOWED');

        $this->assertSame('void', $invoice->refresh()->status);
        $this->assertDatabaseCount('invoice_events', 2);
    }

    public function test_invoice_without_lines_cannot_be_issued(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = Invoice::factory()->create(['owner_id' => $user->id]);

        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", ['status' => 'issued'])
            ->assertConflict()
            ->assertJsonPath('code', 'INVOICE_TRANSITION_NOT_ALLOWED');

        $this->assertDatabaseCount('invoice_events', 0);
    }

    public function test_lifecycle_and_history_are_owner_scoped_and_authenticated(): void
    {
        $invoice = Invoice::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/invoices/{$invoice->id}/transitions", ['status' => 'void'])
            ->assertNotFound();
        $this->getJson("/api/v1/invoices/{$invoice->id}/history")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/invoices/{$invoice->id}/history")
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');
    }

    private function invoiceWithLine(User $owner): Invoice
    {
        $invoice = Invoice::factory()->create(['owner_id' => $owner->id]);
        $item = CatalogItem::factory()->create(['owner_id' => $owner->id]);
        $invoice->lines()->create([
            'catalog_item_id' => $item->id,
            'description' => $item->name,
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        return $invoice;
    }
}
