<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:draft,issued,partially_paid,paid,void'],
            'customer_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:invoice_number,issue_date,due_date,total,created_at,updated_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Invoice::query()
            ->where('owner_id', $request->user()->id)
            ->with('customer');

        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('invoice_number', 'like', $search)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $search));
            });
        }

        foreach (['status', 'customer_id'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $invoices = $query->orderBy($sortBy, $sortDir)->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(
            code: 'INVOICES_RETRIEVED',
            message: 'Invoices retrieved',
            data: InvoiceResource::collection($invoices->items())->resolve(),
            page: [
                'current' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last' => $invoices->lastPage(),
            ],
            sort: ['by' => $sortBy, 'direction' => $sortDir],
            filters: [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
            ],
        );
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $service): JsonResponse
    {
        $invoice = $service->createDraft($request->user(), $request->validated());

        return ApiResponse::success(
            code: 'INVOICE_CREATED',
            message: 'Draft invoice created',
            data: (new InvoiceResource($invoice))->resolve(),
            status: 201,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->ownedInvoice($request, $id)->load(['customer', 'lines']);

        return ApiResponse::success(
            code: 'INVOICE_RETRIEVED',
            message: 'Invoice retrieved',
            data: (new InvoiceResource($invoice))->resolve(),
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = $this->ownedInvoice($request, $id);
        if ($invoice->status !== 'draft') {
            return ApiResponse::error(
                code: 'INVOICE_NOT_DELETABLE',
                message: 'Only draft invoices can be deleted',
                error: null,
                status: 409,
            );
        }

        $invoice->delete();

        return ApiResponse::success(
            code: 'INVOICE_DELETED',
            message: 'Draft invoice deleted',
        );
    }

    private function ownedInvoice(Request $request, int $id): Invoice
    {
        return Invoice::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);
    }
}
