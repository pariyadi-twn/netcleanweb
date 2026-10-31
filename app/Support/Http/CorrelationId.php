<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class CorrelationId
{
    public static function current(): string
    {
        return (string) (Context::get('correlation_id') ?: Str::uuid());
    }

    public static function resolve(Request $request): string
    {
        $candidate = (string) $request->header('X-Correlation-ID');

        return preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $candidate) === 1
            ? $candidate
            : (string) Str::uuid();
    }
}
