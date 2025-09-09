<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Company;

class CompanyAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Get the authenticated user
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Get company_id from JWT token
            $payload = JWTAuth::parseToken()->getPayload();
            $companyId = $payload->get('company_id');
            
            if (!$companyId) {
                return response()->json(['error' => 'Company ID not found in token'], 400);
            }

            // Verify company exists and is active in current tenant
            $company = Company::where('id', $companyId)
                              ->where('status', true)
                              ->first();
            
            if (!$company) {
                return response()->json(['error' => 'Company not found or inactive'], 404);
            }

            // Verify user belongs to this company
            if ($user->company_id !== $companyId) {
                return response()->json(['error' => 'User does not belong to this company'], 403);
            }

            // Add company to request for easy access in controllers
            $request->merge(['current_company' => $company]);
            
            return $next($request);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
        }
    }
}