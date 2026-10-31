<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\PasswordResetLinkRequest;
use App\Modules\Identity\Actions\RequestPasswordReset;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;

class PasswordResetLinkController extends Controller
{
    public function store(PasswordResetLinkRequest $request, RequestPasswordReset $reset): JsonResponse
    {
        $reset->handle($request->string('email')->toString());

        return response()->json([
            'data' => ['status' => 'accepted'],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 202);
    }
}
