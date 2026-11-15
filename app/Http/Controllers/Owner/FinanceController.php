<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\CloseCashSession;
use App\Modules\Cash\Actions\CreateExpense;
use App\Modules\Cash\Actions\DecideExpense;
use App\Modules\Cash\Actions\OpenCashSession;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\Expense;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Enums\PermissionCode;
use App\Modules\Identity\Models\Branch;
use App\Modules\Payment\Actions\DecideRefund;
use App\Modules\Payment\Actions\RequestRefund;
use App\Modules\Payment\Actions\TransitionRefund;
use App\Modules\Payment\Enums\RefundStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $branchIds = $context->branchIds;
        $canCash = $context->can(PermissionCode::CashView);
        $canPayments = $context->can(PermissionCode::PaymentsView);
        $canExpenses = $context->can(PermissionCode::ExpensesManage) || $context->can(PermissionCode::ExpensesApprove);
        $canRefunds = $context->can(PermissionCode::RefundsRequest) || $context->can(PermissionCode::RefundsApprove);

        $sessions = $canCash
            ? CashSession::query()->where('tenant_id', $context->tenantId)->whereHas('register', fn ($q) => $q->whereIn('branch_id', $branchIds))->with('register.branch')->latest('opened_at')->limit(6)->get()
            : collect();
        $payments = $canPayments
            ? Payment::query()->where('tenant_id', $context->tenantId)->whereIn('received_branch_id', $branchIds)->with(['customer', 'receivedBranch'])->latest('received_at')->limit(6)->get()
            : collect();
        $expenses = $canExpenses
            ? Expense::query()->where('tenant_id', $context->tenantId)->whereIn('branch_id', $branchIds)->with('branch')->latest('created_at')->limit(6)->get()
            : collect();
        $refunds = $canRefunds
            ? Refund::query()->where('tenant_id', $context->tenantId)->whereHas('payment', fn ($q) => $q->whereIn('received_branch_id', $branchIds))->with('payment')->latest('created_at')->limit(6)->get()
            : collect();

        return view('owner.finance.index', [
            'user' => $request->user(),
            'sessions' => $sessions,
            'payments' => $payments,
            'expenses' => $expenses,
            'refunds' => $refunds,
            'canCash' => $canCash,
            'canPayments' => $canPayments,
            'canExpenses' => $canExpenses,
            'canRefunds' => $canRefunds,
        ]);
    }

    public function cash(Request $request, TenantContext $context): View
    {
        abort_unless($context->can(PermissionCode::CashView), 403);

        $registers = CashRegister::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('branch_id', $context->branchIds)
            ->with(['branch', 'currentSession.cashier'])
            ->orderBy('code')
            ->get();

        $sessions = CashSession::query()
            ->where('tenant_id', $context->tenantId)
            ->whereHas('register', fn ($query) => $query->whereIn('branch_id', $context->branchIds))
            ->with(['register.branch', 'cashier'])
            ->latest('opened_at')
            ->limit(20)
            ->get();

        return view('owner.finance.cash', [
            'user' => $request->user(),
            'registers' => $registers,
            'sessions' => $sessions,
            'canManage' => $context->can(PermissionCode::CashManage),
        ]);
    }

    public function openCash(Request $request, string $register, TenantContext $context, OpenCashSession $open): RedirectResponse
    {
        $model = CashRegister::query()->where('tenant_id', $context->tenantId)->where('public_id', $register)->with('branch')->firstOrFail();
        abort_unless($context->canAccessBranch($model->branch), 403);
        Gate::authorize('open', [CashSession::class, $model]);

        $validated = $request->validate(['opening_rupiah' => ['required', 'integer', 'min:0']]);

        try {
            $open->handle($model, (int) $validated['opening_rupiah'], $context->membershipId);
        } catch (ApiException $exception) {
            return back()->withErrors(['cash' => $exception->getMessage()]);
        }

        return back()->with('status', 'Shift kas dibuka.');
    }

    public function closeCash(Request $request, string $session, TenantContext $context, CloseCashSession $close): RedirectResponse
    {
        $model = CashSession::query()->where('tenant_id', $context->tenantId)->where('public_id', $session)->with('register.branch')->firstOrFail();
        abort_unless($context->canAccessBranch($model->register->branch), 403);
        Gate::authorize('close', $model);

        $validated = $request->validate([
            'counted_closing_rupiah' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $close->handle($model, (int) $validated['counted_closing_rupiah'], $validated['reason'] ?? null, $context->membershipId);
        } catch (ApiException $exception) {
            return back()->withErrors(['cash' => $exception->getMessage()]);
        }

        return back()->with('status', 'Shift kas ditutup.');
    }

    public function payments(Request $request, TenantContext $context): View
    {
        abort_unless($context->can(PermissionCode::PaymentsView), 403);

        $payments = Payment::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('received_branch_id', $context->branchIds)
            ->with(['customer', 'receivedBranch', 'allocations'])
            ->latest('received_at')
            ->paginate(25)
            ->withQueryString();

        return view('owner.finance.payments', ['user' => $request->user(), 'payments' => $payments]);
    }

    public function requestRefund(Request $request, string $payment, TenantContext $context, RequestRefund $requestRefund): RedirectResponse
    {
        $model = Payment::query()->where('tenant_id', $context->tenantId)->where('public_id', $payment)->with('receivedBranch')->firstOrFail();
        Gate::authorize('requestRefund', $model);
        $validated = $request->validate([
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $requestRefund->handle($model, (int) $validated['amount_rupiah'], $validated['reason'], $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Permintaan refund dibuat.');
    }

    public function expenses(Request $request, TenantContext $context): View
    {
        abort_unless($context->can(PermissionCode::ExpensesManage) || $context->can(PermissionCode::ExpensesApprove), 403);

        $expenses = Expense::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('branch_id', $context->branchIds)
            ->with(['branch', 'cashSession', 'requestedBy'])
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('owner.finance.expenses', [
            'user' => $request->user(),
            'expenses' => $expenses,
            'branches' => Branch::query()->where('tenant_id', $context->tenantId)->whereIn('id', $context->branchIds)->orderBy('name')->get(),
            'sessions' => CashSession::query()->where('tenant_id', $context->tenantId)->where('status', 'open')->whereHas('register', fn ($q) => $q->whereIn('branch_id', $context->branchIds))->with('register.branch')->get(),
            'canManage' => $context->can(PermissionCode::ExpensesManage),
            'canApprove' => $context->can(PermissionCode::ExpensesApprove),
        ]);
    }

    public function storeExpense(Request $request, TenantContext $context, CreateExpense $create): RedirectResponse
    {
        abort_unless($context->can(PermissionCode::ExpensesManage), 403);
        $validated = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:2000'],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
        ]);
        $branch = Branch::query()->where('tenant_id', $context->tenantId)->where('public_id', $validated['branch_id'])->firstOrFail();
        Gate::authorize('create', [Expense::class, $branch]);

        try {
            $create->handle($branch, $validated, $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['expense' => $exception->getMessage()]);
        }

        return back()->with('status', 'Biaya operasional dicatat sebagai draft.');
    }

    public function decideExpense(Request $request, string $expense, TenantContext $context, DecideExpense $decide): RedirectResponse
    {
        $model = Expense::query()->where('tenant_id', $context->tenantId)->where('public_id', $expense)->firstOrFail();
        Gate::authorize('decide', $model);
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'cash_session_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $decide->handle($model, $validated['decision'] === 'approved', $validated['cash_session_id'] ?? null, $context->membershipId, $validated['reason'] ?? null, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['expense' => $exception->getMessage()]);
        }

        return back()->with('status', 'Keputusan biaya disimpan.');
    }

    public function refunds(Request $request, TenantContext $context): View
    {
        abort_unless($context->can(PermissionCode::RefundsRequest) || $context->can(PermissionCode::RefundsApprove), 403);

        $refunds = Refund::query()
            ->where('tenant_id', $context->tenantId)
            ->whereHas('payment', fn ($q) => $q->whereIn('received_branch_id', $context->branchIds))
            ->with(['payment.customer', 'payment.receivedBranch', 'requestedBy', 'approvedBy', 'cashSession'])
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('owner.finance.refunds', [
            'user' => $request->user(),
            'refunds' => $refunds,
            'canApprove' => $context->can(PermissionCode::RefundsApprove),
            'sessions' => CashSession::query()->where('tenant_id', $context->tenantId)->where('status', 'open')->whereHas('register', fn ($q) => $q->whereIn('branch_id', $context->branchIds))->with('register.branch')->get(),
        ]);
    }

    public function decideRefund(Request $request, string $refund, TenantContext $context, DecideRefund $decide): RedirectResponse
    {
        $model = Refund::query()->where('tenant_id', $context->tenantId)->where('public_id', $refund)->firstOrFail();
        Gate::authorize('decide', $model);
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $decide->handle($model, $validated['decision'] === 'approved', $context->membershipId, $validated['reason'] ?? null, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Keputusan refund disimpan.');
    }

    public function transitionRefund(Request $request, string $refund, TenantContext $context, TransitionRefund $transition): RedirectResponse
    {
        $model = Refund::query()->where('tenant_id', $context->tenantId)->where('public_id', $refund)->firstOrFail();
        Gate::authorize('transition', $model);
        $validated = $request->validate([
            'status' => ['required', 'in:processing,succeeded,failed,cancelled'],
            'cash_session_id' => ['nullable', 'uuid'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $transition->handle($model, RefundStatus::from($validated['status']), $context->membershipId, $validated['cash_session_id'] ?? null, $validated['external_ref'] ?? null, $validated['reason'] ?? null, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Status refund diperbarui.');
    }
}
