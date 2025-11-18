<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IoTController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ESP32 IoT Device Routes - No authentication required for IoT devices
Route::prefix('iot')->group(function () {
    // ==================== AUTHENTICATION & ACCESS ====================
    // Authenticate RFID/Fingerprint for box access
    Route::post('/authenticate', [IoTController::class, 'authenticate']);
    
    // Log access attempts (legacy endpoint)
    Route::post('/access-log', [IoTController::class, 'logAccess']);
    
    // ==================== KEY MANAGEMENT ====================
    // Log key checkout/checkin transactions
    Route::post('/key-transaction', [IoTController::class, 'keyTransaction']);
    
    // ==================== PHOTO UPLOADS ====================
    // Upload photo from ESP32-CAM
    Route::post('/upload-photo', [IoTController::class, 'uploadPhoto']);
    
    // ==================== ALERTS & ERRORS ====================
    // Create system alert (door left open, RFID not tapped, etc.)
    Route::post('/alert', [IoTController::class, 'createAlert']);
    
    // ==================== DEVICE MANAGEMENT ====================
    // Update device status (online/offline/error)
    Route::post('/device-status', [IoTController::class, 'updateDeviceStatus']);
    
    // Device heartbeat for health monitoring
    Route::post('/heartbeat', [IoTController::class, 'heartbeat']);
    
    // ==================== SYSTEM STATUS ====================
    // Get current system status and statistics
    Route::get('/status', [IoTController::class, 'getSystemStatus']);
    
    // Get authorized users for a terminal
    Route::get('/authorized-users/{terminal}', [IoTController::class, 'getAuthorizedUsers']);
    
    // ==================== GENERAL EVENT LOGGING ====================
    // Log general IoT events (flexible endpoint)
    Route::post('/event', [IoTController::class, 'logEvent']);
    
    // ==================== REMOTE CONTROL ====================
    // Remote door control from dashboard
    Route::post('/door-control', [IoTController::class, 'controlDoor']);
});

// ==================== INTELLI-LOCK SPECIFIC ROUTES ====================
// Routes matching your ESP32 code implementation
Route::prefix('intellilock')->group(function () {
    // Simple connectivity ping for ESP32 devices
    Route::get('/ping', [IoTController::class, 'ping']);
    // Main event endpoint - matches your ESP32 sendEvent() function
    Route::post('/event', [IoTController::class, 'intellilockEvent']);
    
    // Key transaction endpoint - handles checkout/checkin with user tracking
    Route::post('/key-transaction', [IoTController::class, 'intellilockKeyTransaction']);
    
    // Photo upload from ESP32-CAM (triggered by CAM_TRIGGER pin)
    Route::post('/upload', [IoTController::class, 'intellilockPhotoUpload']);
    
    // Get current system state
    Route::get('/status', [IoTController::class, 'intellilockStatus']);
});

// ==================== KEY CHECK ENDPOINT ====================
// ESP32 calls this to check if an RFID tag is a key
Route::get('/keys/check', [IoTController::class, 'checkKeyTag']);
