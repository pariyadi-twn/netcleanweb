<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Identity\Authorization\TenantContext;
use App\Modules\Identity\Models\Location;
use App\Modules\Order\Models\Order;
use App\Modules\Production\Actions\PlanProductionTasks;
use App\Modules\Production\Actions\RecordQcResult;
use App\Modules\Production\Actions\TransitionProductionTask;
use App\Modules\Production\Enums\ProductionTaskStatus;
use App\Modules\Production\Models\ProductionTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        Gate::authorize('viewAny', ProductionTask::class);

        $locations = Location::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('id', $context->locationIds)
            ->orderBy('name')
            ->get();

        $selected = $locations->firstWhere('public_id', $request->string('location')->toString()) ?? $locations->first();

        $tasks = collect();
        if ($selected !== null) {
            $tasks = ProductionTask::query()
                ->where('tenant_id', $context->tenantId)
                ->where('production_location_id', $selected->id)
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
                ->when($request->boolean('overdue'), fn ($query) => $query->where('due_at', '<', now())->whereNotIn('status', ['done', 'skipped']))
                ->with(['content.orderLine', 'content.order', 'content.bag', 'location'])
                ->orderBy('due_at')
                ->orderBy('sequence')
                ->paginate(30)
                ->withQueryString();
        }

        return view('owner.production.index', [
            'user' => $request->user(),
            'locations' => $locations,
            'selected' => $selected,
            'tasks' => $tasks,
            'statuses' => ProductionTaskStatus::cases(),
        ]);
    }

    public function show(string $task, TenantContext $context): View
    {
        $model = ProductionTask::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $task)
            ->with(['content.order', 'content.orderLine', 'content.bag', 'location', 'qcResults', 'events'])
            ->firstOrFail();

        abort_unless($context->canAccessLocation($model->location), 403);
        Gate::authorize('viewAny', ProductionTask::class);

        return view('owner.production.show', [
            'user' => auth()->user(),
            'task' => $model,
        ]);
    }

    public function transition(Request $request, string $task, TenantContext $context, TransitionProductionTask $transition): RedirectResponse
    {
        $model = $this->findTask($task, $context);
        Gate::authorize('transition', $model);
        $validated = $request->validate([
            'to_status' => ['required', 'string', 'in:queued,in_progress,done,blocked,skipped'],
            'expected_version' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $transition->handle(
                $model,
                ProductionTaskStatus::from($validated['to_status']),
                (int) $validated['expected_version'],
                $context->membershipId,
                $validated['reason'] ?? null,
                $request->user(),
            );
        } catch (ApiException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        return back()->with('status', 'Status produksi diperbarui.');
    }

    public function qc(Request $request, string $task, TenantContext $context, RecordQcResult $record): RedirectResponse
    {
        $model = $this->findTask($task, $context);
        Gate::authorize('recordQc', $model);
        $validated = $request->validate([
            'outcome' => ['required', 'in:passed,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $record->handle(
                $model,
                $validated['outcome'],
                [],
                $validated['notes'] ?? null,
                $context->membershipId,
                $request->user(),
            );
        } catch (ApiException $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }

        return back()->with('status', $validated['outcome'] === 'passed' ? 'QC lulus dicatat.' : 'QC gagal dicatat.');
    }

    public function plan(Request $request, string $order, TenantContext $context, PlanProductionTasks $plan): RedirectResponse
    {
        $orderModel = Order::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $order)
            ->firstOrFail();
        Gate::authorize('view', $orderModel);

        $validated = $request->validate(['location_id' => ['required', 'uuid']]);
        $location = Location::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $validated['location_id'])
            ->firstOrFail();
        Gate::authorize('plan', [ProductionTask::class, $location]);

        try {
            $plan->handle($orderModel, $location, $context->membershipId, $request->user());
        } catch (ApiException $exception) {
            return back()->withErrors(['production' => $exception->getMessage()]);
        }

        return back()->with('status', 'Rencana produksi dibuat.');
    }

    private function findTask(string $publicId, TenantContext $context): ProductionTask
    {
        $model = ProductionTask::query()
            ->where('tenant_id', $context->tenantId)
            ->where('public_id', $publicId)
            ->with(['location', 'content.order'])
            ->firstOrFail();

        abort_unless($context->canAccessLocation($model->location), 403);

        return $model;
    }
}
