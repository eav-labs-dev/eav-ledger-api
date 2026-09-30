<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\TransitionInvoiceRequest;
use App\Http\Resources\InvoiceEventResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceLifecycleService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceLifecycleController extends Controller
{
    public function transition(
        TransitionInvoiceRequest $request,
        int $invoice,
        InvoiceLifecycleService $service,
    ): JsonResponse {
        try {
            $updated = $service->transition($request->user(), $invoice, $request->validated());
        } catch (DomainException $exception) {
            return ApiResponse::error(
                code: 'INVOICE_TRANSITION_NOT_ALLOWED',
                message: $exception->getMessage(),
                error: null,
                status: 409,
            );
        }

        return ApiResponse::success(
            code: 'INVOICE_TRANSITIONED',
            message: 'Invoice status updated',
            data: (new InvoiceResource($updated))->resolve(),
        );
    }

    public function history(Request $request, int $invoice): JsonResponse
    {
        $ownedInvoice = Invoice::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($invoice);

        $events = $ownedInvoice->events()
            ->with('actor')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            code: 'INVOICE_HISTORY_RETRIEVED',
            message: 'Invoice history retrieved',
            data: InvoiceEventResource::collection($events)->resolve(),
        );
    }
}
