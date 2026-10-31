<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $response = $next($request);
        $duration = (int) round((hrtime(true) - $start) / 1_000_000);
        $key = now()->utc()->format('YmdHi');

        foreach ([
            'ops:requests:'.$key => 1,
            'ops:latency:'.$key => $duration,
            'ops:errors:'.$key => $response->getStatusCode() >= 500 ? 1 : 0,
        ] as $metric => $value) {
            Cache::add($metric, 0, 3600);
            Cache::increment($metric, $value);
        }

        return $response;
    }
}
