<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\CreatePriceBook;
use App\Modules\Catalog\Actions\CreatePriceEntry;
use App\Modules\Catalog\Actions\CreateService;
use App\Modules\Catalog\Actions\SetServicePublication;
use App\Modules\Catalog\Models\PriceBook;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\WorkflowTemplate;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Service::class);
        Gate::authorize('viewAny', PriceBook::class);

        $context = app(TenantContext::class);

        return view('owner.catalog.index', [
            'user' => $request->user(),
            'services' => Service::query()
                ->with('workflowTemplate')
                ->orderBy('code')
                ->get(),
            'workflows' => WorkflowTemplate::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
            'priceBooks' => PriceBook::query()
                ->with(['branch', 'entries.service'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'branches' => Branch::query()
                ->whereIn('id', $context->branchIds)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeService(Request $request, CreateService $create): RedirectResponse
    {
        Gate::authorize('create', Service::class);

        $context = app(TenantContext::class);
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('services', 'code')->where('tenant_id', $context->tenantId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', Rule::in(['kg', 'pcs', 'pair', 'meter', 'package'])],
            'minimum_quantity' => ['required', 'string', 'numeric', 'min:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'quantity_step' => ['required', 'string', 'numeric', 'gt:0', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,3})?$/'],
            'workflow_template_id' => ['required', 'uuid'],
        ]);

        $create->handle($context->tenantId, $validated);

        return redirect()
            ->route('catalog.index')
            ->with('status', 'Layanan draft dibuat. Tambahkan harga lalu publikasikan.');
    }

    public function toggleService(Request $request, Service $service, SetServicePublication $publish): RedirectResponse
    {
        abort_unless($service->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('update', $service);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $publish->handle(
            $service,
            $request->boolean('is_active'),
            $request->user(),
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('catalog.index')
            ->with('status', $request->boolean('is_active') ? 'Layanan dipublikasikan.' : 'Layanan dinonaktifkan.');
    }

    public function storePriceBook(
        Request $request,
        CreatePriceBook $create,
    ): RedirectResponse {
        Gate::authorize('create', PriceBook::class);

        $context = app(TenantContext::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => [
                'nullable', 'uuid',
                Rule::exists('branches', 'public_id')->where('tenant_id', $context->tenantId),
            ],
        ]);

        if ($validated['branch_id'] ?? null) {
            $branch = Branch::query()
                ->where('public_id', $validated['branch_id'])
                ->firstOrFail();
            abort_unless($context->canAccessBranch($branch), 403);
        }

        $create->handle($context->tenantId, $validated);

        return redirect()
            ->route('catalog.index')
            ->with('status', 'Price book dibuat.');
    }

    public function storePriceEntry(
        Request $request,
        PriceBook $priceBook,
        CreatePriceEntry $create,
    ): RedirectResponse {
        abort_unless($priceBook->tenant_id === app(TenantContext::class)->tenantId, 404);
        Gate::authorize('update', $priceBook);

        $context = app(TenantContext::class);
        $validated = $request->validate([
            'service_id' => [
                'required', 'uuid',
                Rule::exists('services', 'public_id')->where('tenant_id', $context->tenantId),
            ],
            'tier' => ['required', 'string', Rule::in(['regular', 'express'])],
            'unit_price_rupiah' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ]);

        $create->handle($priceBook, $validated);

        return redirect()
            ->route('catalog.index')
            ->with('status', 'Harga layanan ditambahkan.');
    }
}
