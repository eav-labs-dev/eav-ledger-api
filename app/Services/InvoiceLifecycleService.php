<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class InvoiceLifecycleService
{
    /**
     * @param  array{status: string, note?: string|null}  $data
     */
    public function transition(User $actor, int $invoiceId, array $data): Invoice
    {
        return DB::transaction(function () use ($actor, $invoiceId, $data): Invoice {
            $invoice = Invoice::query()
                ->where('owner_id', $actor->id)
                ->lockForUpdate()
                ->findOrFail($invoiceId);

            $allowed = match ($invoice->status) {
                'draft' => ['issued', 'void'],
                'issued' => ['void'],
                default => [],
            };

            if (! in_array($data['status'], $allowed, true)) {
                throw new DomainException("Invoice cannot transition from {$invoice->status} to {$data['status']}.");
            }

            if ($data['status'] === 'issued' && ! $invoice->lines()->exists()) {
                throw new DomainException('An invoice must have at least one line before it can be issued.');
            }

            $fromStatus = $invoice->status;
            $invoice->update(['status' => $data['status']]);
            $invoice->events()->create([
                'actor_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $data['status'],
                'note' => $data['note'] ?? null,
                'occurred_at' => now(),
            ]);

            return $invoice->refresh()->load(['customer', 'lines']);
        });
    }
}
