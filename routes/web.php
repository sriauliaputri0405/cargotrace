<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

require __DIR__.'/auth.php';
