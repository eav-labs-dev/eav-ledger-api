<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function index(Request $request, int $invoice): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $ownedInvoice = Invoice::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($invoice);
        $payments = $ownedInvoice->payments()
            ->latest('paid_at')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(
            code: 'PAYMENTS_RETRIEVED',
            message: 'Payments retrieved',
            data: PaymentResource::collection($payments->items())->resolve(),
            page: [
                'current' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last' => $payments->lastPage(),
            ],
        );
    }

    public function store(
        StorePaymentRequest $request,
        int $invoice,
        PaymentService $service,
    ): JsonResponse {
        try {
            $result = $service->record($request->user(), $invoice, $request->validated());
        } catch (DomainException $exception) {
            return ApiResponse::error(
                code: 'PAYMENT_NOT_ALLOWED',
                message: $exception->getMessage(),
                error: null,
                status: 409,
            );
        }

        return ApiResponse::success(
            code: 'PAYMENT_RECORDED',
            message: 'Payment recorded',
            data: [
                'payment' => (new PaymentResource($result['payment']))->resolve(),
                'invoice' => [
                    'id' => $result['invoice']->id,
                    'invoice_number' => $result['invoice']->invoice_number,
                    'status' => $result['invoice']->status,
                    'currency' => $result['invoice']->currency,
                    'total' => $result['invoice']->total,
                    'amount_paid' => $result['amount_paid'],
                    'balance_due' => $result['balance_due'],
                ],
            ],
            status: 201,
        );
    }

    public function show(Request $request, int $payment): JsonResponse
    {
        $ownedPayment = Payment::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($payment);

        return ApiResponse::success(
            code: 'PAYMENT_RETRIEVED',
            message: 'Payment retrieved',
            data: (new PaymentResource($ownedPayment))->resolve(),
        );
    }
}
