<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{payment: Payment, invoice: Invoice, amount_paid: string, balance_due: string}
     */
    public function record(User $owner, int $invoiceId, array $data): array
    {
        return DB::transaction(function () use ($owner, $invoiceId, $data): array {
            $invoice = Invoice::query()
                ->where('owner_id', $owner->id)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            if (! in_array($invoice->status, ['issued', 'partially_paid'], true)) {
                throw new DomainException('Payments can only be recorded against issued invoices with an outstanding balance.');
            }

            $totalMinor = $this->toMinor($invoice->total);
            $paidMinor = $invoice->payments()
                ->get(['amount'])
                ->sum(fn (Payment $payment): int => $this->toMinor($payment->amount));
            $amountMinor = $this->toMinor($data['amount']);
            $balanceMinor = $totalMinor - $paidMinor;

            if ($amountMinor > $balanceMinor) {
                throw new DomainException('Payment amount exceeds the invoice balance.');
            }

            $payment = $invoice->payments()->create([
                'owner_id' => $owner->id,
                'payment_number' => 'PAY-'.Str::ulid(),
                'amount' => $this->fromMinor($amountMinor),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaidMinor = $paidMinor + $amountMinor;
            $newStatus = $newPaidMinor === $totalMinor ? 'paid' : 'partially_paid';
            $previousStatus = $invoice->status;
            $invoice->update(['status' => $newStatus]);

            if ($previousStatus !== $newStatus) {
                $invoice->events()->create([
                    'actor_id' => $owner->id,
                    'from_status' => $previousStatus,
                    'to_status' => $newStatus,
                    'note' => "Payment {$payment->payment_number} recorded",
                    'occurred_at' => now(),
                ]);
            }

            return [
                'payment' => $payment,
                'invoice' => $invoice->refresh(),
                'amount_paid' => $this->fromMinor($newPaidMinor),
                'balance_due' => $this->fromMinor($totalMinor - $newPaidMinor),
            ];
        });
    }

    private function toMinor(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromMinor(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
