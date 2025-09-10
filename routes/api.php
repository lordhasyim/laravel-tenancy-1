<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Public routes (no tenant required for login)

// Tenant-specific routes (require X-Tenant-Id header)
Route::middleware(['tenant.identify'])->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    // Registration requires tenant context (to check company exists)
    Route::post('register', [AuthController::class, 'register']);
});
// Protected routes (require JWT)
Route::middleware('tenant.identify', 'auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    // Company-specific routes (require employee access)
    Route::middleware('company.access')->group(function () {
        // Will be populated with actual endpoints later
        Route::get('dashboard', function (Request $request) {
            return response()->json([
                'message' => 'Dashboard data',
                'employee' => $request->current_employee->name ?? 'N/A',
                'company' => $request->current_company->name ?? 'N/A'
            ]);
        });
    });
});
