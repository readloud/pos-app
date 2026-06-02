<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BranchScopeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // Admin can see all branches
        if ($user->role->name === 'admin') {
            return $next($request);
        }
        
        // Other roles only see their branch
        if ($user->branch_id) {
            $request->merge(['branch_id' => $user->branch_id]);
            
            // Add branch scope to request
            $request->attributes->set('branch_scoped', true);
            $request->attributes->set('branch_id', $user->branch_id);
        }
        
        return $next($request);
    }
}