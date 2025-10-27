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
use Carbon\Carbon;

class IoTController extends Controller
{
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

        AccessLog::create([
            'user' => $request->user,
            'type' => $request->type,
            'timestamp' => now(),
            'status' => $request->status,
            'role' => $request->role ?? 'guest',
            'device' => $request->device
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
                                ->whereDate('timestamp', today())
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
     * Handles events:
     * - door_unlocked (fingerprint or RFID opens door)
     * - key_tagged_taken (first RFID tap - checkout)
     * - key_tagged_returned (second RFID tap - checkin)
     * - fingerprint_match (fingerprint verified)
     * - door_timeout_alert (door left open > 20 seconds)
     */
    public function intellilockEvent(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string',
            'extra' => 'nullable|string'
        ]);

        $action = $request->action;
        $extra = $request->extra ?? '';
        $device = 'lab_key_box'; // Default device name

        try {
            switch ($action) {
                case 'door_unlocked':
                    // Log door unlock event
                    $this->logAccessAttempt(
                        'System',
                        'door_unlock',
                        'success',
                        'system',
                        $device
                    );
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Door unlock logged',
                        'action' => $action
                    ]);

                case 'fingerprint_match':
                    // Log fingerprint authentication
                    $this->logAccessAttempt(
                        'Fingerprint User',
                        'fingerprint',
                        'success',
                        'user',
                        $device
                    );
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Fingerprint match logged',
                        'action' => $action
                    ]);

                case 'key_tagged_taken':
                    // Handle key checkout
                    $keyRfidUid = $extra;
                    
                    if (empty($keyRfidUid)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key RFID UID required'
                        ], 400);
                    }

                    // Find the key
                    $labKey = LabKey::where('key_rfid_uid', strtoupper($keyRfidUid))->first();
                    
                    if (!$labKey) {
                        // Create new key if not exists
                        $labKey = LabKey::create([
                            'key_name' => 'Key ' . strtoupper($keyRfidUid),
                            'key_rfid_uid' => strtoupper($keyRfidUid),
                            'description' => 'Auto-registered key',
                            'status' => 'checked_out'
                        ]);
                    } else {
                        $labKey->update(['status' => 'checked_out']);
                    }

                    // Create transaction
                    $transaction = KeyTransaction::create([
                        'lab_key_id' => $labKey->id,
                        'user_name' => 'Unknown User', // Will be updated if user info available
                        'action' => 'checkout',
                        'transaction_time' => now(),
                        'device' => $device
                    ]);

                    // Log the event
                    $this->logAccessAttempt(
                        'Unknown User',
                        'key_checkout',
                        'success',
                        'user',
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
                    // Handle key checkin
                    $keyRfidUid = $extra;
                    
                    if (empty($keyRfidUid)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key RFID UID required'
                        ], 400);
                    }

                    // Find the key
                    $labKey = LabKey::where('key_rfid_uid', strtoupper($keyRfidUid))->first();
                    
                    if (!$labKey) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Key not found'
                        ], 404);
                    }

                    // Update key status
                    $labKey->update(['status' => 'available']);

                    // Create transaction
                    $transaction = KeyTransaction::create([
                        'lab_key_id' => $labKey->id,
                        'user_name' => 'Unknown User',
                        'action' => 'checkin',
                        'transaction_time' => now(),
                        'device' => $device
                    ]);

                    // Log the event
                    $this->logAccessAttempt(
                        'Unknown User',
                        'key_checkin',
                        'success',
                        'user',
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
                    // Create alert for door left open
                    $alert = SystemAlert::create([
                        'device' => $device,
                        'alert_type' => 'door_left_open',
                        'severity' => 'high',
                        'title' => 'Door Timeout Alert',
                        'description' => 'Door was left open for more than 20 seconds or key tag was not scanned',
                        'alert_time' => now(),
                        'status' => 'active'
                    ]);

                    // Log as access event
                    $this->logAccessAttempt(
                        'System',
                        'alert_timeout',
                        'denied',
                        'system',
                        $device
                    );

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Timeout alert created',
                        'alert_id' => $alert->id,
                        'action' => $action
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
        } catch (\Exception $e) {
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
     */
    public function intellilockPhotoUpload(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|image|max:5120', // Max 5MB
            'event_type' => 'nullable|string'
        ]);

        try {
            $eventType = $request->event_type ?? 'access';
            $device = 'lab_key_box';
            
            // Generate unique filename
            $filename = time() . '_' . $device . '_' . uniqid() . '.' . $request->photo->extension();
            
            // Store in public/storage/photos directory
            $path = $request->photo->storeAs('photos', $filename, 'public');
            
            // Get the most recent access log or transaction
            $recentAccessLog = AccessLog::where('device', $device)
                                       ->latest('timestamp')
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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Photo upload failed: ' . $e->getMessage()
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
                                 ->latest('timestamp')
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
                                ->whereDate('timestamp', today())
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
        $accessLog = AccessLog::create([
            'user' => $user,
            'type' => $type,
            'timestamp' => now(),
            'status' => $status,
            'role' => $role,
            'device' => $device,
            'lab_key_id' => $keyId,
            'key_name' => $keyName
        ]);

        // Broadcast access attempt
        broadcast(new AccessAttemptLogged($accessLog));
    }
}
