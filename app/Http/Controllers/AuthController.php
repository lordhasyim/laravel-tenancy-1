<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Company;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     */
    public function __construct()
    {
        // $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $credentials = $request->only('email', 'password');

        // Attempt to authenticate user in current tenant context
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = Auth::guard('api')->user();
        
        // Verify user is active
        if (!$user->status) {
            return response()->json(['error' => 'User account is inactive'], 403);
        }

        // Verify company is active
        $company = Company::find($user->company_id);
        if (!$company || !$company->status) {
            return response()->json(['error' => 'Company is inactive'], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    /**
     * Get the authenticated User.
     */
    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $user->load('company', 'roles.permissions');
        
        return response()->json([
            'user' => $user,
            'tenant_id' => tenant('id'),
            'company' => $user->company,
            'permissions' => $user->getAllPermissions(),
            'roles' => $user->roles,
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        $token = Auth::guard('api')->refresh();
        $user = Auth::guard('api')->user();
        
        return $this->respondWithToken($token, $user);
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => $user->only(['id', 'name', 'email', 'company_id']),
            'tenant_id' => tenant('id'),
            'company_id' => $user->company_id,
        ]);
    }
}