<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        if (!in_array($request->user()->role->name, $roles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        return $next($request);
    }
}