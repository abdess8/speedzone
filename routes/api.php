<?php

use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DriverZoneController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTransitionController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SectorController;
use App\Http\Controllers\Api\UserRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.read');
    Route::post('permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create');
    Route::get('permissions/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.read');
    Route::put('permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.read');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

    Route::put('users/{user}/roles', [UserRoleController::class, 'update'])
        ->middleware('permission:users.roles.assign');
    Route::delete('users/{user}/roles', [UserRoleController::class, 'destroy'])
        ->middleware('permission:users.roles.assign');

    // Cities (delivery destinations)
    Route::get('cities', [CityController::class, 'index'])->middleware('permission:cities.read');
    Route::get('cities/{city}/sectors', [CityController::class, 'sectors'])->middleware('permission:cities.read');
    Route::get('cities/{city}', [CityController::class, 'show'])->middleware('permission:cities.read');
    Route::post('cities', [CityController::class, 'store'])->middleware('permission:cities.create');
    Route::put('cities/{city}', [CityController::class, 'update'])->middleware('permission:cities.update');
    Route::delete('cities/{city}', [CityController::class, 'destroy'])->middleware('permission:cities.delete');

    // Sectors (delivery zones with their own pricing)
    Route::get('sectors', [SectorController::class, 'index'])->middleware('permission:sectors.read');
    Route::get('sectors/{sector}', [SectorController::class, 'show'])->middleware('permission:sectors.read');
    Route::post('sectors', [SectorController::class, 'store'])->middleware('permission:sectors.create');
    Route::put('sectors/{sector}', [SectorController::class, 'update'])->middleware('permission:sectors.update');
    Route::delete('sectors/{sector}', [SectorController::class, 'destroy'])->middleware('permission:sectors.delete');

    // Driver zone assignments
    Route::get('drivers/{driver}/sectors', [DriverZoneController::class, 'index'])->middleware('permission:driver_zones.read');
    Route::post('drivers/{driver}/sectors', [DriverZoneController::class, 'store'])->middleware('permission:driver_zones.assign');
    Route::delete('drivers/{driver}/sectors/{sector}', [DriverZoneController::class, 'destroy'])->middleware('permission:driver_zones.remove');

    // Orders
    Route::get('orders/{order}/tracking', [OrderController::class, 'tracking'])
        ->whereNumber('order')->name('api.orders.tracking');
    Route::get('orders/{order}/pdf', [OrderController::class, 'pdf'])
        ->whereNumber('order')->name('api.orders.pdf');
    Route::get('orders/track/{trackingNumber}', [OrderController::class, 'trackByNumber'])
        ->name('api.orders.track');
    Route::post('orders/{order}/transition', OrderTransitionController::class)
        ->whereNumber('order')->name('api.orders.transition');
    Route::apiResource('orders', OrderController::class)
        ->whereNumber('order')
        ->names('api.orders');
});
