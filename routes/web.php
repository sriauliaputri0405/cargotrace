<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ExceptionController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
    
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', fn () => 'Area Administrator');
});

Route::middleware(['auth', 'role:admin,operator'])->prefix('operations')->group(function () {
    Route::get('/', fn () => 'Area Operasional (admin dan operator)');
});

Route::middleware(['auth', 'role:customer,admin'])->prefix('my-shipments')->group(function () {
    Route::get('/', fn () => 'Area Customer');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('carriers', CarrierController::class)->except('show');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('carriers', CarrierController::class)->except('show');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('carriers', CarrierController::class)->except('show');
    Route::resource('locations', LocationController::class)->except('show');
});

Route::middleware('auth')->group(function () {
    Route::resource('shipments', ShipmentController::class);
    Route::patch('shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])
        ->name('shipments.status');
});

Route::middleware('auth')->group(function () {
    Route::get('tracking', [TrackingController::class, 'index'])->name('tracking.index');
});

Route::middleware(['auth', 'role:admin,operator'])->group(function () {
    Route::post('shipments/{shipment}/tracking', [TrackingController::class, 'store'])->name('tracking.store');
    Route::patch('shipments/{shipment}/eta', [TrackingController::class, 'updateEta'])->name('shipments.eta');

    Route::get('exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
    Route::post('shipments/{shipment}/exceptions', [ExceptionController::class, 'store'])->name('exceptions.store');
    Route::patch('exceptions/{exception}', [ExceptionController::class, 'updateStatus'])->name('exceptions.status');
});

Route::middleware('auth')->group(function () {
    Route::get('shipments/{shipment}/weather', [IntegrationController::class, 'weather'])
        ->middleware('throttle:30,1')->name('shipments.weather');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('geocode', [IntegrationController::class, 'geocode'])
        ->middleware('throttle:10,1')->name('geocode.search');
});

Route::middleware(['auth', 'role:admin,operator'])->group(function () {
    Route::get('fx-rate', [IntegrationController::class, 'fxRate'])
        ->middleware('throttle:20,1')->name('fx.rate');
});

Route::middleware(['auth', 'role:admin,operator'])->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
});

Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
require __DIR__.'/auth.php';
