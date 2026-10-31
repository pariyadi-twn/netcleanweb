<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\PasswordResetRequest;
use App\Modules\Identity\Actions\ResetPassword;
use App\Support\Http\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function store(PasswordResetRequest $request, ResetPassword $reset): JsonResponse
    {
        $status = $reset->handle($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json([
            'data' => ['status' => 'password_reset'],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ]);
    }
}
