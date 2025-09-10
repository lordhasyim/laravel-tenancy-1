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
use Illuminate\Support\Str;

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
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'company_id' => 'required|uuid|exists:companies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // Verify company is active
        $company = Company::find($request->company_id);
        if (!$company || !$company->status) {
            return response()->json([
                'error' => 'Company not found or inactive'
            ], 404);
        }

        try {
            $user = User::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $request->company_id,
                'status' => true,
            ]);

            // Generate JWT token for the new user
            $token = Auth::guard('api')->login($user);

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user->only(['id', 'name', 'email', 'company_id']),
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => (int) Auth::guard('api')->factory()->getTTL() * 60,
                'tenant_id' => tenant('id'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 500);
        }
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
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            // Attempt to authenticate user in current tenant context
            if (!$token = Auth::guard('api')->attempt($credentials)) {
                return response()->json([
                    'error' => 'Invalid credentials'
                ], 401);
            }

            $user = Auth::guard('api')->user();

            // Verify user is active
            if (!$user->status) {
                Auth::guard('api')->logout();
                return response()->json([
                    'error' => 'User account is inactive'
                ], 403);
            }

            // Verify company is active
            $company = Company::find($user->company_id);
            if (!$company || !$company->status) {
                Auth::guard('api')->logout();
                return response()->json([
                    'error' => 'Company is inactive'
                ], 403);
            }

            return $this->respondWithToken($token, $user);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Login failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the authenticated User.
     */
    public function me(): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $user->load('company');

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_id' => $user->company_id,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                ],
                'tenant_id' => tenant('id'),
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'email' => $user->company->email,
                    'status' => $user->company->status,
                ] : null,
                'permissions' => [], // Will be populated when Spatie is implemented
                'roles' => [], // Will be populated when Spatie is implemented
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get user info',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        try {
            Auth::guard('api')->logout();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = Auth::guard('api')->refresh();
            $user = Auth::guard('api')->user();

            return $this->respondWithToken($token, $user);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Token refresh failed',
                'message' => $e->getMessage()
            ], 401);
        }
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'status' => $user->status,
            ],
            'tenant_id' => tenant('id'),
            'company_id' => $user->company_id,
        ]);
    }
}