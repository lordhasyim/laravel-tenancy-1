<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Central\Tenant;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant 
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-Id');
        
        if (!$tenantId) {
            return response()->json([
                'error' => 'Tenant ID is required',
                'message' => 'Please provide X-Tenant-Id header'
            ], 400);
        }

        // Find tenant in central database
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant || !$tenant->status) {
            return response()->json([
                'error' => 'Invalid tenant',
                'message' => 'Tenant not found or inactive'
            ], 404);
        }

        // Initialize tenancy
        tenancy()->initialize($tenant);

        return $next($request);
    }
}