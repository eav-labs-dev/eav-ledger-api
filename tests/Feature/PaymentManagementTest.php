<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_updates_balance_status_and_audit_history(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->issuedInvoice($user, 100);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->payload(40))
            ->assertCreated()
            ->assertJsonPath('code', 'PAYMENT_RECORDED')
            ->assertJsonPath('data.payment.amount', '40.00')
            ->assertJsonPath('data.invoice.status', 'partially_paid')
            ->assertJsonPath('data.invoice.amount_paid', '40.00')
            ->assertJsonPath('data.invoice.balance_due', '60.00');

        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'from_status' => 'issued',
            'to_status' => 'partially_paid',
        ]);
    }

    public function test_second_payment_can_settle_invoice_without_overwriting_history(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->issuedInvoice($user, 100);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->payload(40, 'FIRST'))->assertCreated();
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->payload(60, 'FINAL'))
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.amount_paid', '100.00')
            ->assertJsonPath('data.invoice.balance_due', '0.00');

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('invoice_events', 2);
    }

    public function test_payment_cannot_exceed_outstanding_balance(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->issuedInvoice($user, 100);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->payload(100.01))
            ->assertConflict()
            ->assertJsonPath('code', 'PAYMENT_NOT_ALLOWED');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('issued', $invoice->refresh()->status);
    }

    public function test_payments_are_rejected_for_draft_void_and_paid_invoices(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        foreach (['draft', 'void', 'paid'] as $status) {
            $invoice = Invoice::factory()->create([
                'owner_id' => $user->id,
                'status' => $status,
                'total' => 100,
            ]);

            $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->payload(10, $status))
                ->assertConflict()
                ->assertJsonPath('code', 'PAYMENT_NOT_ALLOWED');
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_reference_is_idempotent_per_owner(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $first = $this->issuedInvoice($user, 100);
        $second = $this->issuedInvoice($user, 100);

        $this->postJson("/api/v1/invoices/{$first->id}/payments", $this->payload(10, 'bank-001'))
            ->assertCreated()
            ->assertJsonPath('data.payment.reference', 'BANK-001');

        $this->postJson("/api/v1/invoices/{$second->id}/payments", $this->payload(10, 'bank-001'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_payments_can_be_listed_with_bounded_pagination(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $invoice = $this->issuedInvoice($user, 1000);
        Payment::factory()->count(3)->create([
            'owner_id' => $user->id,
            'invoice_id' => $invoice->id,
            'amount' => 10,
        ]);

        $this->getJson("/api/v1/invoices/{$invoice->id}/payments?per_page=2")
            ->assertOk()
            ->assertJsonPath('code', 'PAYMENTS_RETRIEVED')
            ->assertJsonPath('page.total', 3)
            ->assertJsonPath('page.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_payments_are_owner_scoped(): void
    {
        $payment = Payment::factory()->create();
        Sanctum::actingAs($user = User::factory()->create());
        $ownInvoice = $this->issuedInvoice($user, 100);

        $this->getJson("/api/v1/payments/{$payment->id}")->assertNotFound();
        $this->getJson("/api/v1/invoices/{$payment->invoice_id}/payments")->assertNotFound();
        $this->postJson("/api/v1/invoices/{$payment->invoice_id}/payments", $this->payload(10))->assertNotFound();

        $ownPayment = Payment::factory()->create([
            'owner_id' => $user->id,
            'invoice_id' => $ownInvoice->id,
        ]);
        $this->getJson("/api/v1/payments/{$ownPayment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownPayment->id);
    }

    public function test_payment_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/invoices/1/payments', $this->payload(10))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');
        $this->getJson('/api/v1/payments/1')->assertUnauthorized();
    }

    private function issuedInvoice(User $owner, float $total): Invoice
    {
        return Invoice::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'issued',
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(float $amount, string $reference = 'PAYMENT-001'): array
    {
        return [
            'amount' => $amount,
            'method' => 'bank_transfer',
            'reference' => $reference,
            'paid_at' => '2026-09-29T08:00:00Z',
        ];
    }
}
