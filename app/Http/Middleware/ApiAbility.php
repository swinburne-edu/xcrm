<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token ability check for the REST API: api.ability:read for GET endpoints, api.ability:write for the others. A token
 * is created with read, or read and write; requests that are not made with a token (signed-in browser) pass.
 */
class ApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! $request->user()?->tokenCan($ability)) {
            return response()->json(['message' => "Token không có quyền {$ability}."], 403);
        }

        return $next($request);
    }
}
