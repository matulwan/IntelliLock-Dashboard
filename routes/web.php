<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\AccessLogController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\KeyManagementController;
use App\Http\Controllers\SecuritySnapsController;
use App\Http\Controllers\DeviceController;

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect('/overview');
    }
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('overview', [OverviewController::class, 'index'])->name('overview');
    
    Route::get('/access-logs', [AccessLogController::class, 'index'])->name('access-logs');
    
    Route::get('security-snaps', [SecuritySnapsController::class, 'index'])->name('security-snaps');
    
    // Device Management
    Route::get('/devices', [DeviceController::class, 'dashboard'])->name('devices');
    
    // Key Management System
    Route::get('/key-management', [KeyManagementController::class, 'index'])->name('key-management');
    Route::put('/key-management/{labKey}', [KeyManagementController::class, 'update'])->name('key-management.update');
    Route::delete('/key-management/{labKey}', [KeyManagementController::class, 'destroy'])->name('key-management.destroy');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management');
    Route::get('/user-management/add', [UserManagementController::class, 'create'])->name('user-management.add');
    Route::post('/user-management', [UserManagementController::class, 'store'])->name('user-management.store');
});

// API routes for devices
Route::middleware(['auth'])->group(function () {
    Route::get('/api/devices', [DeviceController::class, 'index']);
    Route::get('/api/devices/{id}', [DeviceController::class, 'show']);
    Route::put('/api/devices/{id}', [DeviceController::class, 'update']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';