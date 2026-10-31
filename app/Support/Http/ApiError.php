<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    /**
     * @param  array<string, list<string>>  $fieldErrors
     * @param  array<string, string>  $headers
     */
    public static function response(string $code, string $message, int $status, array $fieldErrors = [], array $headers = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'field_errors' => (object) $fieldErrors,
            ],
            'meta' => ['correlation_id' => CorrelationId::current()],
        ], $status, $headers);
    }
}
