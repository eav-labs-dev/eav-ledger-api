<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvoiceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(User $owner, array $data): Invoice
    {
        return DB::transaction(function () use ($owner, $data): Invoice {
            $customer = Customer::query()
                ->where('owner_id', $owner->id)
                ->where('status', 'active')
                ->find($data['customer_id']);

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer must be an active customer that you own.'],
                ]);
            }

            /** @var array<int, array<string, mixed>> $lineData */
            $lineData = $data['lines'];
            $catalogIds = collect($lineData)->pluck('catalog_item_id')->map(fn (mixed $id): int => (int) $id);
            $items = CatalogItem::query()
                ->where('owner_id', $owner->id)
                ->where('status', 'active')
                ->whereIn('id', $catalogIds)
                ->get()
                ->keyBy('id');

            if ($items->count() !== $catalogIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'lines' => ['Every catalog item must be active and owned by the authenticated user.'],
                ]);
            }

            $currency = (string) $data['currency'];
            $mismatched = $items->first(fn (CatalogItem $item): bool => $item->currency !== $currency);
            if ($mismatched) {
                throw ValidationException::withMessages([
                    'currency' => ['Invoice and catalog item currencies must match.'],
                ]);
            }

            $invoice = Invoice::query()->create([
                'owner_id' => $owner->id,
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-'.Str::ulid(),
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $currency,
                'subtotal' => 0,
                'tax_total' => 0,
                'total' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $totals = $this->createLines($invoice, $lineData, $items);
            $invoice->update($totals);

            return $invoice->load(['customer', 'lines']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineData
     * @param  Collection<int, CatalogItem>  $items
     * @return array{subtotal: string, tax_total: string, total: string}
     */
    private function createLines(Invoice $invoice, array $lineData, Collection $items): array
    {
        $subtotalMinor = 0;
        $taxMinor = 0;

        foreach ($lineData as $line) {
            /** @var CatalogItem $item */
            $item = $items->get((int) $line['catalog_item_id']);
            $quantityThousandths = (int) round((float) $line['quantity'] * 1000);
            $unitMinor = (int) round((float) ($line['unit_price'] ?? $item->unit_price) * 100);
            $taxBasisPoints = (int) round((float) ($line['tax_rate'] ?? 0) * 100);
            $lineSubtotalMinor = (int) round($unitMinor * $quantityThousandths / 1000);
            $lineTaxMinor = (int) round($lineSubtotalMinor * $taxBasisPoints / 10000);
            $lineTotalMinor = $lineSubtotalMinor + $lineTaxMinor;

            $invoice->lines()->create([
                'catalog_item_id' => $item->id,
                'description' => $line['description'] ?? $item->name,
                'quantity' => number_format($quantityThousandths / 1000, 3, '.', ''),
                'unit_price' => $this->minorToDecimal($unitMinor),
                'tax_rate' => number_format($taxBasisPoints / 100, 2, '.', ''),
                'subtotal' => $this->minorToDecimal($lineSubtotalMinor),
                'tax_total' => $this->minorToDecimal($lineTaxMinor),
                'total' => $this->minorToDecimal($lineTotalMinor),
            ]);

            $subtotalMinor += $lineSubtotalMinor;
            $taxMinor += $lineTaxMinor;
        }

        return [
            'subtotal' => $this->minorToDecimal($subtotalMinor),
            'tax_total' => $this->minorToDecimal($taxMinor),
            'total' => $this->minorToDecimal($subtotalMinor + $taxMinor),
        ];
    }

    private function minorToDecimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
