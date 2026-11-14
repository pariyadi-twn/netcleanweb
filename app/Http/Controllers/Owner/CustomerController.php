<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Actions\SearchCustomers;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, SearchCustomers $search): View
    {
        Gate::authorize('viewAny', Customer::class);

        $context = app(TenantContext::class);
        $query = trim((string) $request->input('q', ''));
        $customers = $search->handle(
            $context->tenantId,
            $query,
            25,
            $request->input('cursor'),
        )->withQueryString();

        return view('owner.customers.index', [
            'user' => $request->user(),
            'customers' => $customers,
            'query' => $query,
        ]);
    }

    public function store(Request $request, CreateCustomer $create): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'address_label' => ['nullable', 'string', 'max:60', 'required_with:address'],
            'address' => ['nullable', 'string', 'max:2000', 'required_with:address_label'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if (! empty($validated['address'])) {
            $attributes['addresses'] = [[
                'label' => $validated['address_label'],
                'address' => $validated['address'],
                'is_default' => true,
            ]];
        }

        $create->handle(
            app(TenantContext::class)->tenantId,
            $attributes,
            $request->user(),
        );

        return redirect()
            ->route('customers.index')
            ->with('status', 'Pelanggan ditambahkan.');
    }
}
