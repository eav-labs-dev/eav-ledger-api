<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:name,created_at,updated_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Customer::query()->where('owner_id', $request->user()->id);

        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('customer_number', 'like', $search);
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $customers = $query->orderBy($sortBy, $sortDir)->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(
            code: 'CUSTOMERS_RETRIEVED',
            message: 'Customers retrieved',
            data: CustomerResource::collection($customers->items())->resolve(),
            page: [
                'current' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last' => $customers->lastPage(),
            ],
            sort: ['by' => $sortBy, 'direction' => $sortDir],
            filters: [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
            'customer_number' => 'CUS-'.Str::ulid(),
        ]);

        return ApiResponse::success(
            code: 'CUSTOMER_CREATED',
            message: 'Customer created',
            data: (new CustomerResource($customer))->resolve(),
            status: 201,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->ownedCustomer($request, $id);

        return ApiResponse::success(
            code: 'CUSTOMER_RETRIEVED',
            message: 'Customer retrieved',
            data: (new CustomerResource($customer))->resolve(),
        );
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = $this->ownedCustomer($request, $id);
        $customer->update($request->validated());

        return ApiResponse::success(
            code: 'CUSTOMER_UPDATED',
            message: 'Customer updated',
            data: (new CustomerResource($customer->refresh()))->resolve(),
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $this->ownedCustomer($request, $id);
        $customer->delete();

        return ApiResponse::success(
            code: 'CUSTOMER_DELETED',
            message: 'Customer deleted',
        );
    }

    private function ownedCustomer(Request $request, int $id): Customer
    {
        return Customer::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);
    }
}
