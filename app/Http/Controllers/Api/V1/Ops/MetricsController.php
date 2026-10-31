<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\OpsMetrics;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __invoke(Request $request, OpsMetrics $metrics): JsonResponse
    {
        $configured = (string) config('ops.metrics_token');

        if ($configured === '') {
            abort(404);
        }

        if (! hash_equals($configured, (string) $request->header('X-Metrics-Token'))) {
            abort(401);
        }

        return response()->json([
            'data' => $metrics->snapshot(),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
