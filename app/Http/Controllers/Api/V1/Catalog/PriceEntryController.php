<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StorePriceEntryRequest;
use App\Http\Requests\Api\V1\Catalog\UpdatePriceEntryRequest;
use App\Http\Resources\Api\V1\PriceEntryResource;
use App\Modules\Catalog\Actions\CreatePriceEntry;
use App\Modules\Catalog\Actions\UpdatePriceEntry;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\PriceEntry;
use App\Modules\Identity\Authorization\TenantContext;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PriceEntryController extends Controller
{
    public function store(StorePriceEntryRequest $request, string $priceBook, CreatePriceEntry $create): JsonResponse
    {
        $book = $this->findPriceBook($priceBook);

        Gate::authorize('update', $book);

        $entry = $create->handle($book, $request->validated());

        return PriceEntryResource::make($entry)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePriceEntryRequest $request, string $priceEntry, UpdatePriceEntry $update): PriceEntryResource
    {
        $entry = $this->findPriceEntry($priceEntry);

        Gate::authorize('update', $entry);

        return new PriceEntryResource($update->handle($entry, $request->validated()));
    }

    private function findPriceBook(string $publicId): PriceBook
    {
        return PriceBook::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->whereNull('customer_id')
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function findPriceEntry(string $publicId): PriceEntry
    {
        return PriceEntry::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
