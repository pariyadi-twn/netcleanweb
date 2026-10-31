<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Order\Actions\ResolvePublicTracking;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;

class PublicTrackingController extends Controller
{
    public function __invoke(string $token, ResolvePublicTracking $resolve): JsonResponse
    {
        return response()->json([
            'data' => $resolve->handle($token),
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
