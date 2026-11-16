<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Catalog\Models\Service;
use App\Modules\Customer\Models\Customer;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Location;
use App\Modules\Logistics\Actions\CreateHandover;
use App\Modules\Logistics\Models\Handover;
use App\Modules\Order\Actions\AcceptOrder;
use App\Modules\Order\Actions\BuildReceipt;
use App\Modules\Order\Actions\CancelOrder;
use App\Modules\Order\Actions\CreateQuote;
use App\Modules\Order\Actions\DecideOrderRevision;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderRevision;
use App\Modules\Order\Models\Quote;
use App\Modules\Payment\Actions\GrantInvoiceCredit;
use App\Modules\Payment\Actions\RecordPayment;
use App\Modules\Payment\Models\CreditApproval;
use App\Modules\Payment\Models\Payment;
use App\Modules\Production\Actions\CreateComplaint;
use App\Modules\Production\Actions\PlanRewash;
use App\Modules\Production\Actions\ResolveComplaint;
use App\Modules\Production\Models\Complaint;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        Gate::authorize('viewAny', Order::class);

        $branches = Branch::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('id', $context->branchIds)
            ->orderBy('name')
            ->get();

        $statuses = collect(OrderStatus::cases())->map(fn (OrderStatus $status): array => [
            'value' => $status->value,
            'label' => str($status->value)->replace('_', ' ')->title()->toString(),
        ]);

        $query = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('sales_branch_id', $context->branchIds)
            ->with(['customer', 'invoice', 'salesBranch'])
            ->latest('accepted_at')
            ->latest('id');

        if ($request->filled('q')) {
            $search = trim((string) $request->string('q'));
            $query->where(function ($query) use ($search): void {
                $query->where('order_number', 'ilike', '%'.$search.'%')
                    ->orWhereHas('customer', fn ($builder) => $builder->where('name', 'ilike', '%'.$search.'%'));
            });
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), array_column($statuses->all(), 'value'), true)) {
            $query->where('operational_status', $request->string('status')->toString());
        }

        if ($request->filled('branch')) {
            $branch = $branches->firstWhere('public_id', $request->string('branch')->toString());
            if ($branch !== null) {
                $query->where('sales_branch_id', $branch->id);
            }
        }

        return view('owner.orders.index', [
            'user' => $request->user(),
            'orders' => $query->paginate(20)->withQueryString(),
            'branches' => $branches,
            'statuses' => $statuses,
        ]);
    }

    public function create(Request $request, TenantContext $context): View
    {
        Gate::authorize('create', Order::class);

        if ($request->boolean('reset')) {
            $request->session()->forget('web_order_quote_id');
        }

        $quote = null;
        $quoteId = $request->session()->get('web_order_quote_id');
        if (is_string($quoteId)) {
            $quote = Quote::query()
                ->where('tenant_id', $context->tenantId)
                ->where('public_id', $quoteId)
                ->where('consumed_at', null)
                ->first();
        }

        return view('owner.orders.create', [
            'user' => $request->user(),
            'branches' => Branch::query()->where('tenant_id', $context->tenantId)->whereIn('id', $context->branchIds)->orderBy('name')->get(),
            'services' => Service::query()->where('tenant_id', $context->tenantId)->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->where('tenant_id', $context->tenantId)->where('is_active', true)->orderBy('name')->limit(100)->get(),
            'quote' => $quote,
        ]);
    }

    public function storeQuote(Request $request, TenantContext $context, CreateQuote $create): RedirectResponse
    {
        Gate::authorize('create', Order::class);
        $validated = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'string', 'regex:/^(0|[1-9][0-9]{0,8})([.,][0-9]{1,3})?$/'],
            'tiers' => ['sometimes', 'array'],
            'tiers.*' => ['nullable', 'in:regular,express'],
        ]);

        $branch = Branch::query()->where('tenant_id', $context->tenantId)->where('public_id', $validated['branch_id'])->firstOrFail();
        abort_unless($context->canAccessBranch($branch), 403);
        $lines = [];
        foreach ($validated['quantities'] as $serviceId => $quantity) {
            $quantity = str_replace(',', '.', trim((string) $quantity));
            if ($quantity === '' || (float) $quantity <= 0) {
                continue;
            }
            $lines[] = [
                'service_id' => (string) $serviceId,
                'measured_quantity' => $quantity,
                'tier' => $validated['tiers'][$serviceId] ?? 'regular',
            ];
        }
        if ($lines === []) {
            return back()->withErrors(['quantities' => 'Isi jumlah minimal satu layanan.'])->withInput();
        }

        try {
            $quote = $create->handle(
                $context->tenantId,
                [
                    'branch_id' => $branch->public_id,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'fulfillment_method' => 'pickup',
                    'pickup_branch_id' => $branch->public_id,
                    'service_discount_percent' => 0,
                    'lines' => $lines,
                ],
                $context->membershipId,
                $context->can(PermissionCode::DiscountsStandard),
                $context->can(PermissionCode::DiscountsOverride),
            );
        } catch (ApiException $exception) {
            return back()->withErrors(['quote' => $exception->getMessage()])->withInput();
        }

        $request->session()->put('web_order_quote_id', $quote->public_id);

        return redirect()->route('orders.create')->with('status', 'Quote berhasil dihitung.');
    }

    public function acceptQuote(Request $request, TenantContext $context, AcceptOrder $accept): RedirectResponse
    {
        Gate::authorize('create', Order::class);
        $quoteId = $request->session()->get('web_order_quote_id');
        $quote = is_string($quoteId)
            ? Quote::query()->where('tenant_id', $context->tenantId)->where('public_id', $quoteId)->first()
            : null;

        if ($quote === null || $quote->consumed_at !== null || $quote->expires_at->isPast()) {
            $request->session()->forget('web_order_quote_id');

            return redirect()->route('orders.create')->withErrors(['quote' => 'Quote tidak tersedia atau sudah kedaluwarsa.']);
        }

        try {
            $order = $accept->handle($quote, $quote->pricing_snapshot['input'], $request->user(), $context->membershipId);
        } catch (ApiException $exception) {
            return back()->withErrors(['quote' => $exception->getMessage()]);
        }

        $request->session()->forget('web_order_quote_id');

        return redirect()->route('orders.show', $order)->with('status', 'Pesanan berhasil dibuat.');
    }

    public function show(string $order, TenantContext $context): View
    {
        $model = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $order)
            ->with([
                'customer',
                'salesBranch',
                'pickupBranch',
                'invoice.adjustments',
                'invoice.allocations',
                'lines',
                'bags.contents.orderLine',
                'events',
                'revisions',
                'printAttempts',
            ])
            ->firstOrFail();

        Gate::authorize('view', $model);

        $complaints = Complaint::query()
            ->where('tenant_id', $context->tenantId)
            ->where('order_id', $model->id)
            ->latest('id')
            ->get();

        $handovers = Handover::query()
            ->where('tenant_id', $context->tenantId)
            ->where('order_id', $model->id)
            ->with('location')
            ->latest('id')
            ->get();

        $tasks = ProductionTask::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('bag_content_id', $model->bags->flatMap->contents->pluck('id'))
            ->with('location')
            ->orderBy('sequence')
            ->get();

        return view('owner.orders.show', [
            'user' => auth()->user(),
            'order' => $model,
            'complaints' => $complaints,
            'handovers' => $handovers,
            'tasks' => $tasks,
            'canCancel' => Gate::forUser(auth()->user())->allows('cancel', $model)
                && (! in_array($model->operational_status, [OrderStatus::Ready, OrderStatus::PartiallyReady], true) || $context->can(PermissionCode::CancellationsApprove)),
            'locations' => Location::query()->where('tenant_id', $context->tenantId)->whereIn('id', $context->locationIds)->orderBy('name')->get(),
            'openSessions' => CashSession::query()->where('tenant_id', $context->tenantId)->where('status', 'open')->whereHas('register', fn ($query) => $query->whereIn('branch_id', $context->branchIds))->with('register.branch')->get(),
            'canHandover' => $context->can(PermissionCode::HandoversManage),
            'canCredit' => $context->can(PermissionCode::CreditManage),
            'canDecideRevision' => $context->can(PermissionCode::DiscountsOverride),
            'pendingRevisions' => $model->revisions->filter(fn ($revision) => $revision->status->value === 'pending'),
            'canComplaints' => $context->can(PermissionCode::ComplaintsManage),
        ]);
    }

    public function cancel(Request $request, string $order, TenantContext $context, CancelOrder $cancel): RedirectResponse
    {
        $model = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $order)
            ->firstOrFail();

        Gate::authorize('cancel', $model);

        $validated = $request->validate([
            'expected_version' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        if (in_array($model->operational_status, [OrderStatus::Ready, OrderStatus::PartiallyReady], true)
            && ! $context->can(PermissionCode::CancellationsApprove)) {
            abort(403);
        }

        try {
            $cancel->handle(
                $model,
                (int) $validated['expected_version'],
                $validated['reason'],
                $context->membershipId,
                $request->user(),
            );
        } catch (ApiException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return redirect()->route('orders.show', $model)->with('status', 'Pesanan dibatalkan.');
    }

    public function recordPayment(Request $request, string $order, TenantContext $context, RecordPayment $record): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->with('invoice')->firstOrFail();
        Gate::authorize('view', $model);
        abort_unless($model->invoice !== null, 404);
        abort_unless($context->canAccessBranch($model->salesBranch), 403);
        Gate::authorize('create', Payment::class);

        $validated = $request->validate([
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,bank_manual'],
            'cash_session_id' => ['nullable', 'uuid'],
            'cash_received_rupiah' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $record->handle(
                $model->invoice,
                [
                    'amount_rupiah' => (int) $validated['amount_rupiah'],
                    'method' => $validated['method'],
                    'cash_session_id' => $validated['cash_session_id'] ?? null,
                    'cash_received_rupiah' => $validated['cash_received_rupiah'] ?? null,
                ],
                $context->membershipId,
                $request->user(),
            );
        } catch (ApiException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', 'Pembayaran dicatat.');
    }

    public function handover(Request $request, string $order, TenantContext $context, CreateHandover $handover): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->firstOrFail();
        Gate::authorize('create', [Handover::class, $model]);
        $validated = $request->validate([
            'bag_ids' => ['required', 'array', 'min:1'],
            'bag_ids.*' => ['uuid'],
            'location_id' => ['required', 'uuid'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'verification_method' => ['required', 'in:receipt,otp,verified_staff_exception'],
            'evidence_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $handover->handle($model, $validated, $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['handover' => $exception->getMessage()]);
        }

        return back()->with('status', 'Serah terima dicatat.');
    }

    public function credit(Request $request, string $order, TenantContext $context, GrantInvoiceCredit $credit): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->with('invoice')->firstOrFail();
        abort_unless($model->invoice !== null, 404);
        Gate::authorize('create', [CreditApproval::class, $model->invoice]);
        $validated = $request->validate([
            'limit_rupiah' => ['required', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $credit->handle($model->invoice, (int) $validated['limit_rupiah'], $validated['expires_at'] ?? null, $validated['reason'], $context->membershipId);

        return back()->with('status', 'Credit approval dicatat.');
    }

    public function decideRevision(Request $request, string $order, string $revision, TenantContext $context, DecideOrderRevision $decide): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->firstOrFail();
        Gate::authorize('revise', $model);
        abort_unless($context->can(PermissionCode::DiscountsOverride), 403);
        $target = OrderRevision::query()->where('tenant_id', $context->tenantId)->where('order_id', $model->id)->where('public_id', $revision)->firstOrFail();
        $validated = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'reason' => ['required', 'string', 'max:2000']]);

        try {
            $decide->handle($target, $validated['decision'] === 'approved', $validated['reason'], $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['revision' => $exception->getMessage()]);
        }

        return back()->with('status', 'Keputusan revisi disimpan.');
    }

    public function storeComplaint(Request $request, string $order, TenantContext $context, CreateComplaint $create): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->firstOrFail();
        Gate::authorize('create', [Complaint::class, $model]);
        $validated = $request->validate([
            'kind' => ['required', 'in:rewash,missing,damaged,other'],
            'description' => ['required', 'string', 'max:2000'],
        ]);
        $create->handle($model, $validated, $context->membershipId, $request->user());

        return back()->with('status', 'Komplain dicatat.');
    }

    public function rewash(Request $request, string $order, string $complaint, TenantContext $context, PlanRewash $rewash): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->firstOrFail();
        $target = Complaint::query()->where('tenant_id', $context->tenantId)->where('order_id', $model->id)->where('public_id', $complaint)->firstOrFail();
        Gate::authorize('manage', $target);

        try {
            $rewash->handle($target, $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['complaint' => $exception->getMessage()]);
        }

        return back()->with('status', 'Rewash dibuat.');
    }

    public function resolveComplaint(Request $request, string $order, string $complaint, TenantContext $context, ResolveComplaint $resolve): RedirectResponse
    {
        $model = Order::query()->where('tenant_id', $context->tenantId)->where('public_id', $order)->firstOrFail();
        $target = Complaint::query()->where('tenant_id', $context->tenantId)->where('order_id', $model->id)->where('public_id', $complaint)->firstOrFail();
        Gate::authorize('manage', $target);
        $validated = $request->validate([
            'status' => ['required', 'in:resolved,rejected'],
            'resolution' => ['required', 'string', 'max:2000'],
        ]);
        try {
            $resolve->handle($target, $validated['status'], $validated['resolution'], null, $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['complaint' => $exception->getMessage()]);
        }

        return back()->with('status', 'Komplain diperbarui.');
    }

    public function receipt(string $order, TenantContext $context, BuildReceipt $build): View
    {
        $model = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $order)
            ->firstOrFail();

        Gate::authorize('view', $model);

        return view('owner.orders.receipt', [
            'user' => auth()->user(),
            'order' => $model,
            'receipt' => $build->handle($model),
        ]);
    }
}
