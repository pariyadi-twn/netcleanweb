<?php

namespace App\Http\Middleware;

use App\Support\Http\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::resolve($request);

        Context::add('correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
