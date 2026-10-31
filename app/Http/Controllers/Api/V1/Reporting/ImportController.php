<?php

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reporting\ImportRequest;
use App\Http\Resources\Api\V1\ImportBatchResource;
use App\Http\Resources\Api\V1\ImportRowResource;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Reporting\Actions\CsvExport;
use App\Modules\Reporting\Actions\RunImport;
use App\Modules\Reporting\Models\ImportBatch;
use App\Modules\Reporting\Models\ImportRow;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ImportController extends Controller
{
    private const TEMPLATES = [
        'customers' => ['name', 'phone', 'email', 'notes', 'address_label', 'address'],
        'catalog' => ['code', 'name', 'unit', 'minimum_quantity', 'quantity_step', 'workflow_template_id', 'sla_mode', 'regular_turnaround', 'express_turnaround'],
    ];

    public function template(string $type, CsvExport $csv): Response
    {
        $this->assertType($type);
        Gate::authorize('create', ImportBatch::class);

        return response($csv->build(self::TEMPLATES[$type], []), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$type.'-template.csv"',
        ]);
    }

    public function store(ImportRequest $request, string $type, RunImport $import): JsonResponse
    {
        $this->assertType($type);
        Gate::authorize('create', ImportBatch::class);

        $context = app(TenantContext::class);
        $mapping = $request->input('mapping', []);
        if (! is_array($mapping)) {
            $mapping = [];
        }

        $batch = $import->handle(
            $context->tenantId,
            $context->membershipId,
            $type,
            $request->file('file'),
            $request->boolean('dry_run'),
            $mapping,
            $request->user(),
        );

        return ImportBatchResource::make($batch)
            ->additional(['meta' => ['correlation_id' => CorrelationId::current()]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $import): ImportBatchResource
    {
        $batch = $this->find($import);
        Gate::authorize('view', $batch);

        return new ImportBatchResource($batch);
    }

    public function rows(Request $request, string $import)
    {
        $batch = $this->find($import);
        Gate::authorize('view', $batch);

        $paginator = ImportRow::query()
            ->where('tenant_id', $batch->tenant_id)
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number')
            ->orderBy('id')
            ->cursorPaginate(max(1, min(100, (int) $request->integer('per_page', 25))), ['*'], 'cursor', $request->input('cursor'));

        return ImportRowResource::collection($paginator->items())->additional([
            'meta' => [
                'correlation_id' => CorrelationId::current(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }

    private function find(string $publicId): ImportBatch
    {
        return ImportBatch::query()
            ->where('tenant_id', app(TenantContext::class)->tenantId)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function assertType(string $type): void
    {
        if (! array_key_exists($type, self::TEMPLATES)) {
            abort(404);
        }
    }
}
