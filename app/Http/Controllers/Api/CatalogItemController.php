<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use App\Http\Resources\CatalogItemResource;
use App\Models\CatalogItem;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CatalogItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:product,service'],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:name,unit_price,created_at,updated_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = CatalogItem::query()->where('owner_id', $request->user()->id);

        if (! empty($validated['search'])) {
            $search = '%'.$validated['search'].'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', $search)
                    ->orWhere('sku', 'like', $search)
                    ->orWhere('catalog_number', 'like', $search);
            });
        }

        foreach (['type', 'status'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $items = $query->orderBy($sortBy, $sortDir)->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success(
            code: 'CATALOG_ITEMS_RETRIEVED',
            message: 'Catalog items retrieved',
            data: CatalogItemResource::collection($items->items())->resolve(),
            page: [
                'current' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last' => $items->lastPage(),
            ],
            sort: ['by' => $sortBy, 'direction' => $sortDir],
            filters: [
                'search' => $validated['search'] ?? null,
                'type' => $validated['type'] ?? null,
                'status' => $validated['status'] ?? null,
            ],
        );
    }

    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $item = CatalogItem::query()->create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
            'catalog_number' => 'ITEM-'.Str::ulid(),
        ]);

        return ApiResponse::success(
            code: 'CATALOG_ITEM_CREATED',
            message: 'Catalog item created',
            data: (new CatalogItemResource($item))->resolve(),
            status: 201,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = $this->ownedItem($request, $id);

        return ApiResponse::success(
            code: 'CATALOG_ITEM_RETRIEVED',
            message: 'Catalog item retrieved',
            data: (new CatalogItemResource($item))->resolve(),
        );
    }

    public function update(UpdateCatalogItemRequest $request, int $id): JsonResponse
    {
        $item = $this->ownedItem($request, $id);
        $item->update($request->validated());

        return ApiResponse::success(
            code: 'CATALOG_ITEM_UPDATED',
            message: 'Catalog item updated',
            data: (new CatalogItemResource($item->refresh()))->resolve(),
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->ownedItem($request, $id);
        $item->delete();

        return ApiResponse::success(
            code: 'CATALOG_ITEM_DELETED',
            message: 'Catalog item deleted',
        );
    }

    private function ownedItem(Request $request, int $id): CatalogItem
    {
        return CatalogItem::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);
    }
}
