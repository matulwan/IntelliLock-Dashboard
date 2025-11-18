<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\IoTDevice;
use App\Models\User;
use App\Models\LabKey;
use App\Models\KeyTransaction;
use App\Models\EventPhoto;
use App\Models\SystemAlert;
use App\Events\DeviceStatusUpdated;
use App\Events\AccessAttemptLogged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IoTController extends Controller
{
    /**
     * Lightweight ping endpoint for ESP32 connectivity tests
     * Endpoint: GET /api/intellilock/ping
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'time' => now()->toIso8601String(),
        ]);
    }
    /**
     * Authenticate RFID/Fingerprint access
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'uid' => 'nullable|string',
            'fingerprint_id' => 'nullable|integer',
            'terminal' => 'required|string',
            'type' => 'required|in:rfid,fingerprint'
        ]);

        $terminal = $request->terminal;
        $type = $request->type;
        $isAuthorized = false;
        $userName = 'Unknown';
        $userRole = 'guest';

        if ($type === 'rfid' && $request->uid) {
            $user = User::where('rfid_uid', strtoupper($request->uid))
                       ->where('iot_access', true)
                       ->first();
            
            if ($user) {
                $isAuthorized = true;
                $userName = $user->name;
                $userRole = $user->role;
            }
        } elseif ($type === 'fingerprint' && $request->fingerprint_id) {
            $user = User::where('fingerprint_id', $request->fingerprint_id)
                       ->where('iot_access', true)
                       ->first();
            
            if ($user) {
                $isAuthorized = true;
                $userName = $user->name;
                $userRole = $user->role;
            }
        }

        // Log the access attempt
        $this->logAccessAttempt($userName, $type, $isAuthorized ? 'success' : 'denied', $userRole, $terminal);

        if ($isAuthorized) {
            return response()->json([
                'status' => 'success',
                'access' => 'door',
                'name' => $userName,
                'message' => 'Access Granted',
                'door_action' => 'open'
            ]);
        } else {
            return response()->json([
                'status' => 'denied',
                'access' => 'none',
                'name' => 'Unknown',
                'message' => 'Access Denied',
                'door_action' => 'none'
            ]);
        }
    }

    /**
     * Log access attempts
     */
    public function logAccess(Request $request): JsonResponse
    {
        $request->validate([
            'user' => 'required|string',
            'type' => 'required|string',
            'status' => 'required|in:success,denied',
            'role' => 'nullable|string',
            'device' => 'required|string'
        ]);

        // Align with access_logs schema: action, user, key_name, device, created_at
        AccessLog::create([
            'user' => $request->user,
            'action' => $request->status, // store success/denied for dashboard stats
            'key_name' => null,
            'device' => $request->device,
        ]);

        return response()->json(['status' => 'logged']);
    }

    /**
     * Update device status
     */
    public function updateDeviceStatus(Request $request): JsonResponse
    {
        $request->validate([
            'terminal' => 'required|string',
            'status' => 'required|in:online,offline,error',
            'ip_address' => 'nullable|ip',
            'wifi_strength' => 'nullable|integer|min:-100|max:0',
            'uptime' => 'nullable|integer',
            'free_memory' => 'nullable|integer'
        ]);

        $device = IoTDevice::updateOrCreate(
            ['terminal_name' => $request->terminal],
            [
                'status' => $request->status,
                'ip_address' => $request->ip_address,
                'wifi_strength' => $request->wifi_strength,
                'uptime' => $request->uptime,
                'free_memory' => $request->free_memory,
                'last_seen' => now()
            ]
        );

        // Broadcast device status update
        broadcast(new DeviceStatusUpdated($device));

        return response()->json(['status' => 'updated', 'device_id' => $device->id]);
    }

    /**
     * Check if an RFID UID belongs to a key tag
     * Endpoint: GET /api/iot/keys/check?uid=FA1AB9B2
     */
    public function checkKeyTag(Request $request): JsonResponse
    {
        $request->validate([
            'uid' => 'required|string',
        ]);

        $uid = strtoupper($request->input('uid'));
        
        // Check if it's a key tag
        $labKey = LabKey::where('key_rfid_uid', $uid)->first();
        
        if ($labKey) {
            return response()->json([
                'is_key' => true,
                'key_name' => $labKey->key_name,
                'key_id' => $labKey->id,
                'status' => $labKey->status,
                'message' => 'This is a key tag'
            ]);
        }
        
        // Check if it's a user RFID
        $user = User::where('rfid_uid', $uid)->where('iot_access', true)->first();
        
        if ($user) {
            return response()->json([
                'is_key' => false,
                'is_user' => true,
                'user_name' => $user->name,
                'user_id' => $user->id,
                'message' => 'This is a user RFID card'
            ]);
        }
        
        // Unknown RFID
        return response()->json([
            'is_key' => false,
            'is_user' => false,
            'message' => 'Unknown RFID tag'
        ]);
    }

    /**
     * Get authorized users for a terminal
     */
    public function getAuthorizedUsers(string $terminal): JsonResponse
    {
        $users = User::where('iot_access', true)
                    ->select('name', 'rfid_uid', 'fingerprint_id', 'role')
                    ->get();

        return response()->json([
            'terminal' => $terminal,
            'users' => $users,
            'count' => $users->count()
        ]);
    }

    /**
     * Control door remotely
     */
    public function controlDoor(Request $request): JsonResponse
    {
        $request->validate([
            'terminal' => 'required|string',
            'action' => 'required|in:open,close,lock,unlock',
            'duration' => 'nullable|integer|min:1|max:30'
        ]);

        // Log remote door control
        $this->logAccessAttempt(
            'Remote Control',
            'remote',
            'success',
            'admin',
            $request->terminal
        );

        return response()->json([
            'status' => 'success',
            'action' => $request->action,
            'terminal' => $request->terminal,
            'duration' => $request->duration ?? 5
        ]);
    }

    /**
     * Device heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'terminal' => 'required|string',
            'timestamp' => 'required|integer'
        ]);

        IoTDevice::updateOrCreate(
            ['terminal_name' => $request->terminal],
            [
                'status' => 'online',
                'last_seen' => now()
            ]
        );

        return response()->json([
            'status' => 'ok',
            'server_time' => time(),
            'message' => 'Heartbeat received'
        ]);
    }

    /**
     * Log key transaction (checkout/checkin)
     */
    public function keyTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'key_rfid_uid' => 'required|string',
            'action' => 'required|in:checkout,checkin',
            'device' => 'required|string',
            'user_rfid_uid' => 'nullable|string',
            'user_fingerprint_id' => 'nullable|integer'
        ]);

        // Find the key
        $labKey = LabKey::where('key_rfid_uid', $request->key_rfid_uid)->first();
        
        if (!$labKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Key not found'
            ], 404);
        }

        // Find the user (from recent box access)
        $user = null;
        if ($request->user_rfid_uid) {
            $user = User::where('rfid_uid', $request->user_rfid_uid)->first();
        } elseif ($request->user_fingerprint_id) {
            $user = User::where('fingerprint_id', $request->user_fingerprint_id)->first();
        }

        // Get the most recent successful access to determine user
        if (!$user) {
            $recentAccess = AccessLog::where('device', $request->device)
                                   ->where('status', 'success')
                                   ->orderBy('timestamp', 'desc')
                                   ->first();
            $userName = $recentAccess ? $recentAccess->user : 'Unknown';
        } else {
            $userName = $user->name;
        }

        // Create key transaction
        KeyTransaction::create([
            'lab_key_id' => $labKey->id,
            'user_name' => $userName,
            'user_rfid_uid' => $request->user_rfid_uid,
            'user_fingerprint_id' => $request->user_fingerprint_id,
            'action' => $request->action,
            'transaction_time' => now(),
            'device' => $request->device
        ]);

        // Update key status
        $labKey->update([
            'status' => $request->action === 'checkout' ? 'checked_out' : 'available'
        ]);

        // Log the key access
        $this->logAccessAttempt(
            $userName,
            'key_' . $request->action,
            'success',
            $user ? $user->role : 'user',
            $request->device,
            $labKey->id,
            $labKey->key_name
        );

        return response()->json([
            'status' => 'success',
            'message' => "Key {$labKey->key_name} {$request->action} logged",
            'key' => $labKey->key_name,
            'action' => $request->action
        ]);
    }

    /**
     * Upload photo from ESP32-CAM
     * Endpoint: POST /api/iot/upload-photo
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|image|max:5120', // Max 5MB
            'device' => 'required|string',
            'event_type' => 'required|in:access,checkout,checkin,alert',
            'access_log_id' => 'nullable|integer|exists:access_logs,id',
            'key_transaction_id' => 'nullable|integer|exists:key_transactions,id',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            // Generate unique filename
            $filename = time() . '_' . $request->device . '_' . uniqid() . '.' . $request->photo->extension();
            
            // Store in public/storage/photos directory
            $path = $request->photo->storeAs('photos', $filename, 'public');
            
            // Create database record
            $eventPhoto = EventPhoto::create([
                'access_log_id' => $request->access_log_id,
                'key_transaction_id' => $request->key_transaction_id,
                'photo_path' => $path,
                'device' => $request->device,
                'event_type' => $request->event_type,
                'notes' => $request->notes
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Photo uploaded successfully',
                'photo_id' => $eventPhoto->id,
                'photo_url' => $eventPhoto->photo_url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Photo upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create system alert
     * Endpoint: POST /api/iot/alert
     */
    public function createAlert(Request $request): JsonResponse
    {
        $request->validate([
            'device' => 'required|string',
            'alert_type' => 'required|in:door_left_open,rfid_not_tapped,sensor_failure,unauthorized_access,low_battery,connection_lost,tamper_detected,other',
            'severity' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'user_name' => 'nullable|string'
        ]);

        $alert = SystemAlert::create([
            'device' => $request->device,
            'alert_type' => $request->alert_type,
            'severity' => $request->severity,
            'title' => $request->title,
            'description' => $request->description,
            'user_name' => $request->user_name,
            'alert_time' => now(),
            'status' => 'active'
        ]);

        // Log critical alerts to access logs as well
        if ($request->severity === 'critical') {
            $this->logAccessAttempt(
                $request->user_name ?? 'System',
                'alert_' . $request->alert_type,
                'denied',
                'system',
                $request->device
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Alert created successfully',
            'alert_id' => $alert->id
        ]);
    }

    /**
     * Get system status and statistics
     * Endpoint: GET /api/iot/status
     */
    public function getSystemStatus(Request $request): JsonResponse
    {
        $device = $request->query('device', 'lab_key_box');
        
        // Get device info
        $deviceInfo = IoTDevice::where('terminal_name', $device)->first();
        
        // Get key statistics
        $totalKeys = LabKey::count();
        $availableKeys = LabKey::where('status', 'available')->count();
        $checkedOutKeys = LabKey::where('status', 'checked_out')->count();
        
        // Get recent alerts
        $activeAlerts = SystemAlert::where('device', $device)
                                   ->active()
                                   ->count();
        
        // Get today's access count
        $todayAccess = AccessLog::where('device', $device)
                                ->whereDate('created_at', today())
                                ->count();

        return response()->json([
            'status' => 'success',
            'device' => [
                'name' => $device,
                'status' => $deviceInfo->status ?? 'offline',
                'last_seen' => $deviceInfo->last_seen ?? null,
                'ip_address' => $deviceInfo->ip_address ?? null
            ],
            'keys' => [
                'total' => $totalKeys,
                'available' => $availableKeys,
                'checked_out' => $checkedOutKeys
            ],
            'alerts' => [
                'active' => $activeAlerts
            ],
            'access' => [
                'today' => $todayAccess
            ],
            'server_time' => now()->toIso8601String()
        ]);
    }

    /**
     * Log general IoT event
     * Endpoint: POST /api/iot/event
     */
    public function logEvent(Request $request): JsonResponse
    {
        $request->validate([
            'device' => 'required|string',
            'event_type' => 'required|string',
            'user_name' => 'nullable|string',
            'user_rfid_uid' => 'nullable|string',
            'user_fingerprint_id' => 'nullable|integer',
            'key_rfid_uid' => 'nullable|string',
            'action' => 'nullable|in:checkout,checkin,access,denied',
            'description' => 'nullable|string'
        ]);

        // Determine what type of event this is
        $eventType = $request->event_type;
        
        // If it's a key transaction
        if ($request->key_rfid_uid && in_array($request->action, ['checkout', 'checkin'])) {
            return $this->keyTransaction($request);
        }
        
        // If it's an access attempt
        if (in_array($eventType, ['rfid', 'fingerprint', 'access'])) {
            $user = null;
            if ($request->user_rfid_uid) {
                $user = User::where('rfid_uid', $request->user_rfid_uid)->first();
            } elseif ($request->user_fingerprint_id) {
                $user = User::where('fingerprint_id', $request->user_fingerprint_id)->first();
            }
            
            $this->logAccessAttempt(
                $request->user_name ?? ($user ? $user->name : 'Unknown'),
                $eventType,
                $request->action ?? 'success',
                $user ? $user->role : 'guest',
                $request->device
            );
            
            return response()->json([
                'status' => 'success',
                'message' => 'Event logged successfully'
            ]);
        }
        
        // Generic event logging
        return response()->json([
            'status' => 'success',
            'message' => 'Event received',
            'event_type' => $eventType
        ]);
    }

    /**
     * ========================================================================
     * INTELLI-LOCK SPECIFIC ENDPOINTS (Matching ESP32 Implementation)
     * ========================================================================
     */

    /**
     * Handle Intelli-Lock events from ESP32
     * Endpoint: POST /api/intellilock/event
     * 
     * ESP32 sends JSON format:
     * {
     *   "action": "user_authenticated|door_unlocked|door_locked|key_taken|key_returned|unknown_key_tag|door_timeout",
     *   "user": "RFID_UID or FP_ID",
     *   "key_info": "Key name or UID",
     *   "timestamp": millis()
     * }
     * 
     * Handles events:
     * - user_authenticated (user authenticated via RFID or fingerprint)
     * - door_unlocked (door unlocked after authentication)
     * - door_locked (door locked after transaction)
     * - key_taken (key checked out)
     * - key_returned (key checked in)
     * - unknown_key_tag (unknown key tag detected)
     * - door_timeout (door left open > 20 seconds)
     */
    public function intellilockEvent(Request $request): JsonResponse
    {
        try {
            // Support both old format (extra) and new format (user, key_info)
            $request->validate([
                'action' => 'required|string',
                'user' => 'nullable|string',      // ESP32 sends this
                'key_info' => 'nullable|string',  // ESP32 sends this
                'extra' => 'nullable|string',     // Legacy support
                'timestamp' => 'nullable|integer'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        }

        $action = $request->action;
        $userIdentifier = $request->user ?? $request->extra ?? '';
        $keyInfo = $request->key_info ?? '';
        $device = 'lab_key_box'; // Default device name

        // Helper function to find user from identifier
        $findUser = function($identifier) {
            if (empty($identifier)) return null;
            
            // Check if it's a fingerprint ID (starts with FP_)
            if (str_starts_with($identifier, 'FP_')) {
                $fingerprintId = (int) str_replace('FP_', '', $identifier);
                return User::where('fingerprint_id', $fingerprintId)
                          ->where('iot_access', true)
                          ->first();
            }
            
            // Otherwise, treat as RFID UID
            return User::where('rfid_uid', strtoupper($identifier))
                      ->where('iot_access', true)
                      ->first();
        };

        try {
            switch ($action) {
                case 'user_authenticated':
                    // User authenticated via RFID or fingerprint
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'Unknown User');
                    $authType = str_starts_with($userIdentifier, 'FP_') ? 'fingerprint' : 'rfid';
                    
                    $this->logAccessAttempt(
                        $userName,
                        $authType,
                        'success',
                        $user ? $user->role : 'user',
                        $device
                    );
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'User authenticated',
                        'action' => $action,
                        'user' => $userName
                    ]);

                case 'door_unlocked':
                    // Door unlocked after authentication
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'System');
                    
                    $this->logAccessAttempt(
                        $userName,
                        'door_unlock',
                        'success',
                        $user ? $user->role : 'user',
                        $device
                    );
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Door unlocked',
                        'action' => $action,
                        'user' => $userName
                    ]);

                case 'door_locked':
                    // Door locked after transaction
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'System');
                    
                    $this->logAccessAttempt(
                        $userName,
                        'door_lock',
                        'success',
                        $user ? $user->role : 'user',
                        $device
                    );
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Door locked',
                        'action' => $action,
                        'user' => $userName
                    ]);

                case 'key_taken':
                    // Key checked out
                    if (empty($keyInfo)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key information required'
                        ], 400);
                    }

                    // Find key by name first (ESP32 sends key name like "Key 1", "Key 2", etc.)
                    // Then try by RFID UID if not found
                    $labKey = LabKey::where('key_name', $keyInfo)->first();
                    
                    if (!$labKey) {
                        // Try by RFID UID
                        $labKey = LabKey::where('key_rfid_uid', strtoupper($keyInfo))->first();
                    }
                    
                    if (!$labKey) {
                        // Create new key if not exists (ESP32 sends key name, so use that)
                        // Note: RFID UID will be updated when key is registered properly
                        $labKey = LabKey::create([
                            'key_name' => $keyInfo,
                            'key_rfid_uid' => null, // Will be set when key is registered
                            'description' => 'Auto-registered key from ESP32',
                            'status' => 'checked_out'
                        ]);
                    } else {
                        $labKey->update(['status' => 'checked_out']);
                    }

                    // Find user
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'Unknown User');

                    // Create transaction
                    $transaction = KeyTransaction::create([
                        'lab_key_id' => $labKey->id,
                        'user_name' => $userName,
                        'user_rfid_uid' => $user && $user->rfid_uid ? strtoupper($user->rfid_uid) : (str_starts_with($userIdentifier, 'FP_') ? null : strtoupper($userIdentifier)),
                        'user_fingerprint_id' => $user ? $user->fingerprint_id : (str_starts_with($userIdentifier, 'FP_') ? (int) str_replace('FP_', '', $userIdentifier) : null),
                        'action' => 'checkout',
                        'transaction_time' => now(),
                        'device' => $device
                    ]);

                    // Log the event
                    $this->logAccessAttempt(
                        $userName,
                        'key_checkout',
                        'success',
                        $user ? $user->role : 'user',
                        $device,
                        $labKey->id,
                        $labKey->key_name
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => "Key {$labKey->key_name} checked out",
                        'key' => $labKey->key_name,
                        'action' => 'checkout',
                        'transaction_id' => $transaction->id
                    ]);

                case 'key_returned':
                    // Key checked in
                    if (empty($keyInfo)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key information required'
                        ], 400);
                    }

                    // Find key by name first (ESP32 sends key name)
                    // Then try by RFID UID if not found
                    $labKey = LabKey::where('key_name', $keyInfo)->first();
                    
                    if (!$labKey) {
                        // Try by RFID UID
                        $labKey = LabKey::where('key_rfid_uid', strtoupper($keyInfo))->first();
                    }
                    
                    if (!$labKey) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key not found. Key must be registered before it can be returned.'
                        ], 404);
                    }

                    // Update key status
                    $labKey->update(['status' => 'available']);

                    // Find user
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'Unknown User');

                    // Create transaction
                    $transaction = KeyTransaction::create([
                        'lab_key_id' => $labKey->id,
                        'user_name' => $userName,
                        'user_rfid_uid' => $user && $user->rfid_uid ? strtoupper($user->rfid_uid) : (str_starts_with($userIdentifier, 'FP_') ? null : strtoupper($userIdentifier)),
                        'user_fingerprint_id' => $user ? $user->fingerprint_id : (str_starts_with($userIdentifier, 'FP_') ? (int) str_replace('FP_', '', $userIdentifier) : null),
                        'action' => 'checkin',
                        'transaction_time' => now(),
                        'device' => $device
                    ]);

                    // Log the event
                    $this->logAccessAttempt(
                        $userName,
                        'key_checkin',
                        'success',
                        $user ? $user->role : 'user',
                        $device,
                        $labKey->id,
                        $labKey->key_name
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => "Key {$labKey->key_name} returned",
                        'key' => $labKey->key_name,
                        'action' => 'checkin',
                        'transaction_id' => $transaction->id
                    ]);

                case 'unknown_key_tag':
                    // Unknown key tag detected
                    $alert = SystemAlert::create([
                        'device' => $device,
                        'alert_type' => 'unauthorized_access',
                        'severity' => 'medium',
                        'title' => 'Unknown Key Tag Detected',
                        'description' => "Unknown key tag detected: {$keyInfo}",
                        'user_name' => $userIdentifier ?: 'Unknown',
                        'alert_time' => now(),
                        'status' => 'active'
                    ]);

                    $this->logAccessAttempt(
                        $userIdentifier ?: 'Unknown',
                        'unknown_key_tag',
                        'denied',
                        'guest',
                        $device
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Unknown key tag alert created',
                        'alert_id' => $alert->id,
                        'action' => $action
                    ]);

                case 'door_timeout':
                    // Door left open timeout
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'System');
                    
                    $alert = SystemAlert::create([
                        'device' => $device,
                        'alert_type' => 'door_left_open',
                        'severity' => 'high',
                        'title' => 'Door Timeout Alert',
                        'description' => 'Door was left open for more than 20 seconds or key tag was not scanned',
                        'user_name' => $userName,
                        'alert_time' => now(),
                        'status' => 'active'
                    ]);

                    $this->logAccessAttempt(
                        $userName,
                        'alert_timeout',
                        'denied',
                        $user ? $user->role : 'system',
                        $device
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Timeout alert created',
                        'alert_id' => $alert->id,
                        'action' => $action
                    ]);

                // Legacy action names for backward compatibility
                case 'fingerprint_match':
                    // Map to user_authenticated
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'Unknown User');
                    $authType = str_starts_with($userIdentifier, 'FP_') ? 'fingerprint' : 'rfid';
                    
                    $this->logAccessAttempt(
                        $userName,
                        $authType,
                        'success',
                        $user ? $user->role : 'user',
                        $device
                    );
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'User authenticated',
                        'action' => 'user_authenticated',
                        'user' => $userName
                    ]);

                case 'key_tagged_taken':
                case 'key_taken':
                    // Process key checkout
                    $keyRfidUid = $keyInfo ?: $userIdentifier;
                    if (empty($keyRfidUid)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key RFID UID required'
                        ], 400);
                    }

                    $keyRfidUid = strtoupper($keyRfidUid);
                    $labKey = LabKey::where('key_name', $keyRfidUid)
                                   ->orWhere('key_rfid_uid', $keyRfidUid)
                                   ->first();
                    
                    if (!$labKey) {
                        $labKey = LabKey::create([
                            'key_name' => "Key {$keyRfidUid}",
                            'key_rfid_uid' => $keyRfidUid,
                            'description' => 'Auto-registered key',
                            'status' => 'checked_out'
                        ]);
                    } elseif ($labKey->status === 'available') {
                        $labKey->update(['status' => 'checked_out']);
                    }

                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'Unknown User');

                    $transaction = KeyTransaction::create([
                        'lab_key_id' => $labKey->id,
                        'user_name' => $userName,
                        'user_rfid_uid' => $user && $user->rfid_uid ? strtoupper($user->rfid_uid) : (str_starts_with($userIdentifier, 'FP_') ? null : strtoupper($userIdentifier)),
                        'user_fingerprint_id' => $user ? $user->fingerprint_id : (str_starts_with($userIdentifier, 'FP_') ? (int) str_replace('FP_', '', $userIdentifier) : null),
                        'action' => 'checkout',
                        'transaction_time' => now(),
                        'device' => $device
                    ]);

                    $this->logAccessAttempt(
                        $userName,
                        'key_checkout',
                        'success',
                        $user ? $user->role : 'user',
                        $device,
                        $labKey->id,
                        $labKey->key_name
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => "Key {$labKey->key_name} checked out",
                        'key' => $labKey->key_name,
                        'action' => 'checkout',
                        'transaction_id' => $transaction->id
                    ]);

                case 'key_tagged_returned':
                    // Map to key_returned
                    $keyRfidUid = $keyInfo ?: $userIdentifier;
                    if (empty($keyRfidUid)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key RFID UID required'
                        ], 400);
                    }

                    $labKey = LabKey::where('key_name', $keyRfidUid)
                                   ->orWhere('key_rfid_uid', strtoupper($keyRfidUid))
                                   ->first();
                    
                    if (!$labKey) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key not found'
                        ], 404);
                    }

                    $labKey->update(['status' => 'available']);

                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'Unknown User');

                    $transaction = KeyTransaction::create([
                        'lab_key_id' => $labKey->id,
                        'user_name' => $userName,
                        'user_rfid_uid' => $user && $user->rfid_uid ? strtoupper($user->rfid_uid) : (str_starts_with($userIdentifier, 'FP_') ? null : strtoupper($userIdentifier)),
                        'user_fingerprint_id' => $user ? $user->fingerprint_id : (str_starts_with($userIdentifier, 'FP_') ? (int) str_replace('FP_', '', $userIdentifier) : null),
                        'action' => 'checkin',
                        'transaction_time' => now(),
                        'device' => $device
                    ]);

                    $this->logAccessAttempt(
                        $userName,
                        'key_checkin',
                        'success',
                        $user ? $user->role : 'user',
                        $device,
                        $labKey->id,
                        $labKey->key_name
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => "Key {$labKey->key_name} returned",
                        'key' => $labKey->key_name,
                        'action' => 'checkin',
                        'transaction_id' => $transaction->id
                    ]);

                case 'door_timeout_alert':
                    // Map to door_timeout
                    $user = $findUser($userIdentifier);
                    $userName = $user ? $user->name : ($userIdentifier ?: 'System');
                    
                    $alert = SystemAlert::create([
                        'device' => $device,
                        'alert_type' => 'door_left_open',
                        'severity' => 'high',
                        'title' => 'Door Timeout Alert',
                        'description' => 'Door was left open for more than 20 seconds or key tag was not scanned',
                        'user_name' => $userName,
                        'alert_time' => now(),
                        'status' => 'active'
                    ]);

                    $this->logAccessAttempt(
                        $userName,
                        'alert_timeout',
                        'denied',
                        $user ? $user->role : 'system',
                        $device
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Timeout alert created',
                        'alert_id' => $alert->id,
                        'action' => 'door_timeout'
                    ]);

                default:
                    // Unknown action - log it anyway
                    $this->logAccessAttempt(
                        'System',
                        'unknown_event',
                        'success',
                        'system',
                        $device
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Event logged',
                        'action' => $action
                    ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('ESP32 event validation failed', [
                'action' => $action,
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors(),
                'action' => $action
            ], 422);
        } catch (\Exception $e) {
            Log::error('ESP32 event processing failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Event processing failed: ' . $e->getMessage(),
                'action' => $action
            ], 500);
        }
    }

    /**
     * Handle photo upload from ESP32-CAM
     * Endpoint: POST /api/intellilock/upload
     * 
     * ESP32-CAM sends raw binary JPEG data with Content-Type: image/jpeg
     * or multipart/form-data with photo field
     */
    public function intellilockPhotoUpload(Request $request): JsonResponse
    {
        try {
            $device = 'lab_key_box';
            $eventType = 'access';
            $imageData = null;
            $extension = 'jpg';
            
            // Check if it's a multipart form upload (legacy support)
            if ($request->hasFile('photo')) {
                $request->validate([
                    'photo' => 'required|file|image|max:5120', // Max 5MB
                    'event_type' => 'nullable|string'
                ]);
                
                $file = $request->file('photo');
                $extension = $file->extension();
                $imageData = file_get_contents($file->getRealPath());
                $eventType = $request->event_type ?? 'access';
            } else {
                // Raw binary JPEG data from ESP32-CAM
                // ESP32-CAM sends Content-Type: image/jpeg with binary body
                $contentType = $request->header('Content-Type');
                
                // Accept image/jpeg, image/jpg, application/octet-stream, or empty (ESP32 might send different headers)
                if (empty($contentType) || str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg') || str_contains($contentType, 'application/octet-stream')) {
                    // Read raw binary data from request body
                    $imageData = $request->getContent();
                    
                    if (empty($imageData)) {
                        Log::warning('ESP32-CAM upload: No image data received', [
                            'content_type' => $contentType,
                            'content_length' => $request->header('Content-Length'),
                            'method' => $request->method()
                        ]);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'No image data received'
                        ], 400);
                    }
                    
                    // Validate it's a JPEG by checking magic bytes
                    if (strlen($imageData) < 2 || substr($imageData, 0, 2) !== "\xFF\xD8") {
                        Log::warning('ESP32-CAM upload: Invalid JPEG format', [
                            'first_bytes' => bin2hex(substr($imageData, 0, 10)),
                            'size' => strlen($imageData)
                        ]);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid JPEG format. Expected JPEG image data.'
                        ], 400);
                    }
                    
                    // Check file size (max 5MB)
                    if (strlen($imageData) > 5 * 1024 * 1024) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Image too large (max 5MB)'
                        ], 400);
                    }
                } else {
                    Log::warning('ESP32-CAM upload: Invalid content type', [
                        'content_type' => $contentType
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid content type. Expected image/jpeg or multipart/form-data. Received: ' . ($contentType ?: 'none')
                    ], 400);
                }
            }
            
            if (empty($imageData)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No image data received'
                ], 400);
            }
            
            // Generate unique filename
            $filename = time() . '_' . $device . '_' . uniqid() . '.' . $extension;
            
            // Store in public/storage/photos directory
            $path = 'photos/' . $filename;
            Storage::disk('public')->put($path, $imageData);
            
            // Get the most recent access log or transaction
            $recentAccessLog = AccessLog::where('device', $device)
                                       ->latest('created_at')
                                       ->first();
            
            $recentTransaction = KeyTransaction::where('device', $device)
                                              ->latest('transaction_time')
                                              ->first();
            
            // Create database record
            $eventPhoto = EventPhoto::create([
                'access_log_id' => $recentAccessLog ? $recentAccessLog->id : null,
                'key_transaction_id' => $recentTransaction ? $recentTransaction->id : null,
                'photo_path' => $path,
                'device' => $device,
                'event_type' => $eventType,
                'notes' => 'Auto-captured by ESP32-CAM'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Photo uploaded successfully',
                'photo_id' => $eventPhoto->id,
                'photo_url' => $eventPhoto->photo_url,
                'filename' => $filename
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ESP32-CAM upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Photo upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle smart key transaction from ESP32
     * Endpoint: POST /api/intellilock/key-transaction
     * 
     * Automatically determines if this is a checkout or checkin based on key status
     */
    public function intellilockKeyTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string',
            'key_rfid_uid' => 'required|string',
            'user_rfid_uid' => 'nullable|string',
            'user_fingerprint_id' => 'nullable|integer'
        ]);

        $device = 'lab_key_box';
        $keyRfidUid = strtoupper($request->key_rfid_uid);

        try {
            // Find or create the key
            $labKey = LabKey::where('key_rfid_uid', $keyRfidUid)->first();
            
            if (!$labKey) {
                $labKey = LabKey::create([
                    'key_name' => 'Key ' . substr($keyRfidUid, -4),
                    'key_rfid_uid' => $keyRfidUid,
                    'description' => 'Auto-registered key',
                    'status' => 'available'
                ]);
            }

            // Determine user
            $user = null;
            $userName = 'Unknown User';
            
            if ($request->user_rfid_uid) {
                $user = User::where('rfid_uid', strtoupper($request->user_rfid_uid))->first();
            } elseif ($request->user_fingerprint_id) {
                $user = User::where('fingerprint_id', $request->user_fingerprint_id)->first();
            }
            
            if ($user) {
                $userName = $user->name;
            }

            // Determine action based on current key status
            $action = $labKey->status === 'available' ? 'checkout' : 'checkin';
            $newStatus = $action === 'checkout' ? 'checked_out' : 'available';

            // Update key status
            $labKey->update(['status' => $newStatus]);

            // Create transaction
            $transaction = KeyTransaction::create([
                'lab_key_id' => $labKey->id,
                'user_name' => $userName,
                'user_rfid_uid' => $request->user_rfid_uid ? strtoupper($request->user_rfid_uid) : null,
                'user_fingerprint_id' => $request->user_fingerprint_id,
                'action' => $action,
                'transaction_time' => now(),
                'device' => $device
            ]);

            // Log the event
            $this->logAccessAttempt(
                $userName,
                'key_' . $action,
                'success',
                $user ? $user->role : 'user',
                $device,
                $labKey->id,
                $labKey->key_name
            );

            return response()->json([
                'status' => 'success',
                'message' => "Key {$labKey->key_name} {$action} by {$userName}",
                'key' => [
                    'id' => $labKey->id,
                    'name' => $labKey->key_name,
                    'rfid' => $labKey->key_rfid_uid,
                    'status' => $newStatus
                ],
                'action' => $action,
                'user' => $userName,
                'transaction_id' => $transaction->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Intelli-Lock system status
     * Endpoint: GET /api/intellilock/status
     */
    public function intellilockStatus(Request $request): JsonResponse
    {
        $device = 'lab_key_box';
        
        // Get device info
        $deviceInfo = IoTDevice::where('terminal_name', $device)->first();
        
        // Get key statistics
        $totalKeys = LabKey::count();
        $availableKeys = LabKey::where('status', 'available')->count();
        $checkedOutKeys = LabKey::where('status', 'checked_out')->count();
        
        // Get recent activity
        $recentAccess = AccessLog::where('device', $device)
                                 ->latest('created_at')
                                 ->limit(5)
                                 ->get();
        
        $recentTransactions = KeyTransaction::where('device', $device)
                                            ->latest('transaction_time')
                                            ->limit(5)
                                            ->get();
        
        // Get active alerts
        $activeAlerts = SystemAlert::where('device', $device)
                                   ->where('status', 'active')
                                   ->count();
        
        // Get today's statistics
        $todayAccess = AccessLog::where('device', $device)
                                ->whereDate('created_at', today())
                                ->count();
        
        $todayCheckouts = KeyTransaction::where('device', $device)
                                       ->where('action', 'checkout')
                                       ->whereDate('transaction_time', today())
                                       ->count();

        return response()->json([
            'status' => 'success',
            'device' => [
                'name' => $device,
                'status' => $deviceInfo->status ?? 'online',
                'last_seen' => $deviceInfo->last_seen ?? now(),
                'ip_address' => $deviceInfo->ip_address ?? 'Unknown'
            ],
            'keys' => [
                'total' => $totalKeys,
                'available' => $availableKeys,
                'checked_out' => $checkedOutKeys
            ],
            'today' => [
                'access_count' => $todayAccess,
                'checkouts' => $todayCheckouts
            ],
            'alerts' => [
                'active' => $activeAlerts
            ],
            'recent_access' => $recentAccess,
            'recent_transactions' => $recentTransactions,
            'server_time' => now()->toIso8601String()
        ]);
    }

    /**
     * Helper method to log access attempts
     */
    private function logAccessAttempt(string $user, string $type, string $status, string $role, string $device, ?int $keyId = null, ?string $keyName = null): void
    {
        // Persist minimal fields matching schema; encode outcome in 'action' for dashboard stats
        $accessLog = AccessLog::create([
            'user' => $user,
            'action' => $status, // success|denied
            'device' => $device,
            'key_name' => $keyName,
        ]);

        // Broadcast access attempt
        broadcast(new AccessAttemptLogged($accessLog));

        // Broadcast dashboard update with latest data
        broadcast(new DashboardDataUpdated(DashboardService::getDashboardData()));
    }
}
