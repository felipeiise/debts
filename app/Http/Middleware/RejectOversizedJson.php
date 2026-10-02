<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RejectOversizedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('services.vehicle_debts.max_request_bytes', 16384);
        if ((int) $request->header('Content-Length', 0) > $limit || strlen($request->getContent()) > $limit) {
            return response()->json(['error' => ['code' => 'request_too_large', 'message' => 'Request body exceeds the allowed size.']], 413);
        }

        return $next($request);
    }
}
