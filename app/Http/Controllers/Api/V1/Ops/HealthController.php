<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\OpsHealth;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(OpsHealth $health): JsonResponse
    {
        $result = $health->check();

        return response()->json([
            'data' => $result,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], $result['status'] === 'ok' ? 200 : 503);
    }
}
