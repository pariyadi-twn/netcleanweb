<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ConfirmMfaRequest;
use App\Http\Requests\Api\V1\Auth\RegenerateRecoveryCodesRequest;
use App\Modules\Identity\Actions\BeginMfaEnrollment;
use App\Modules\Identity\Actions\ConfirmMfaEnrollment;
use App\Modules\Identity\Actions\RegenerateMfaRecoveryCodes;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    public function store(Request $request, BeginMfaEnrollment $begin): JsonResponse
    {
        $enrollment = $begin->handle($request->user());

        return response()->json([
            'data' => $enrollment,
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], 201);
    }

    public function confirm(ConfirmMfaRequest $request, ConfirmMfaEnrollment $confirm): JsonResponse
    {
        $codes = $confirm->handle($request->user(), $request->string('code')->toString());

        return response()->json([
            'data' => ['recovery_codes' => $codes],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }

    public function regenerate(RegenerateRecoveryCodesRequest $request, RegenerateMfaRecoveryCodes $regenerate): JsonResponse
    {
        $codes = $regenerate->handle($request->user(), $request->string('password')->toString());

        return response()->json([
            'data' => ['recovery_codes' => $codes],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
