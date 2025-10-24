<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\AccessLogController;
use App\Http\Controllers\OverviewController;

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect('/overview');
    }
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('overview', [OverviewController::class, 'index'])->name('overview');
    
    Route::get('/access-logs', [AccessLogController::class, 'index'])->name('access-logs');
    
    Route::get('security-snaps', function () {
        return Inertia::render('security-snaps');
    })->name('security-snaps');
    
    // Key Management System
    Route::get('/key-management', function () {
        return Inertia::render('key-management');
    })->name('key-management');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management');
    Route::get('/user-management/add', [UserManagementController::class, 'create'])->name('user-management.add');
    Route::post('/user-management', [UserManagementController::class, 'store'])->name('user-management.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
