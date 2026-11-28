<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {

    // Publik, dibatasi 6 percobaan per menit
    Route::post('register', [Api\AuthController::class, 'register'])->middleware('throttle:6,1')->name('register');
    Route::post('login', [Api\AuthController::class, 'login'])->middleware('throttle:6,1')->name('login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('logout', [Api\AuthController::class, 'logout'])->name('logout');
        Route::get('user', [Api\AuthController::class, 'user'])->name('user');

        Route::apiResource('shipments', Api\ShipmentController::class);
        Route::patch('shipments/{shipment}/status', [Api\ShipmentController::class, 'updateStatus'])->name('shipments.status');
        Route::patch('shipments/{shipment}/eta', [Api\ShipmentController::class, 'updateEta'])->name('shipments.eta');

        Route::get('shipments/{shipment}/tracking', [Api\TrackingController::class, 'index'])->name('tracking.index');
        Route::get('shipments/{shipment}/exceptions', [Api\ExceptionController::class, 'index'])->name('exceptions.index');

        Route::get('carriers', [Api\CarrierController::class, 'index'])->name('carriers.index');
        Route::get('locations', [Api\LocationController::class, 'index'])->name('locations.index');

        // Hanya staff (admin dan operator)
        Route::middleware('role:admin,operator')->group(function () {
            Route::post('shipments/{shipment}/tracking', [Api\TrackingController::class, 'store'])->name('tracking.store');
            Route::post('shipments/{shipment}/exceptions', [Api\ExceptionController::class, 'store'])->name('exceptions.store');
            Route::patch('exceptions/{exception}', [Api\ExceptionController::class, 'updateStatus'])->name('exceptions.status');
        });

        // Hanya admin (CarrierRequest dan LocationRequest memeriksa role admin)
        Route::post('carriers', [Api\CarrierController::class, 'store'])->name('carriers.store');
        Route::post('locations', [Api\LocationController::class, 'store'])->name('locations.store');
    });
});