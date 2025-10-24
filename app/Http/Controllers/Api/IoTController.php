<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\IoTDevice;
use App\Models\User;
use App\Models\LabKey;
use App\Models\KeyTransaction;
use App\Events\DeviceStatusUpdated;
use App\Events\AccessAttemptLogged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
