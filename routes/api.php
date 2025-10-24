<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IoTController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ESP32 IoT Device Routes
Route::prefix('iot')->group(function () {
    // Authentication check for RFID/Fingerprint
    Route::post('/authenticate', [IoTController::class, 'authenticate']);
    
    // Log access attempts
    Route::post('/access-log', [IoTController::class, 'logAccess']);
    
    // Device status updates
    Route::post('/device-status', [IoTController::class, 'updateDeviceStatus']);
    
    // Get authorized users/cards
    Route::get('/authorized-users/{terminal}', [IoTController::class, 'getAuthorizedUsers']);
    
    // Door control
    Route::post('/door-control', [IoTController::class, 'controlDoor']);
    
    // Device heartbeat
    Route::post('/heartbeat', [IoTController::class, 'heartbeat']);
    
    // Key transactions
    Route::post('/key-transaction', [IoTController::class, 'keyTransaction']);
});
