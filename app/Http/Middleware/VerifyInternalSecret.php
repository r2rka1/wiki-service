<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.internal.shared_secret');
        $provided = $request->header('X-Internal-Secret');

        if (! $expected || ! $provided || ! hash_equals((string) $expected, (string) $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! $request->header('X-User-Id')) {
            return response()->json(['message' => 'Missing X-User-Id'], 400);
        }

        return $next($request);
    }
}
