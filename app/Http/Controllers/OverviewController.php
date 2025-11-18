<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\IoTDevice;
use App\Models\LabKey;
use App\Models\User;
use App\Models\KeyTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class OverviewController extends Controller
{
    public function index()
    {
        // Key Box Status
        $keyBox = IoTDevice::where('terminal_name', 'lab_key_box')->first();
        
        // Camera Status - specifically look for camera device
        $cameraDevice = IoTDevice::where('device_type', 'camera')
            ->orWhere('terminal_name', 'like', '%camera%')
            ->orWhere('terminal_name', 'like', '%esp32-cam%')
            ->first();
        
        // If no camera device found, check if we have any device that might be a camera
        if (!$cameraDevice) {
            $cameraDevice = IoTDevice::where('status', 'online')->first();
        }

        // Key Statistics
        $totalKeys = LabKey::count();
        $availableKeys = LabKey::where('status', 'available')->count();
        $checkedOutKeys = LabKey::where('status', 'checked_out')->count();
        
        // User Statistics
        $totalUsers = User::count();
        $iotUsers = User::where('iot_access', true)->count();
        $activeUsers = User::whereHas('accessLogs', function($query) {
            $query->where('timestamp', '>=', now()->subDays(30));
        })->count();
        
        // Access Statistics - Count only unique door unlock events
        $totalAccess = AccessLog::where('action', 'door_unlocked')
            ->distinct()
            ->count();
        $successfulAccess = AccessLog::where('action', 'door_unlocked')
            ->where('status', 'success')
            ->count();
        $failedAccess = AccessLog::where('status', 'denied')
            ->count();
        $todayAccess = AccessLog::where('action', 'door_unlocked')
            ->whereDate('created_at', today())
            ->distinct()
            ->count();
        
        // Recent Activity (Last 10 access attempts)
        $recentActivity = AccessLog::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                $createdAt = optional($log->created_at);
                return [
                    'id' => $log->id,
                    'user' => $log->user,
                    'type' => 'rfid',
                    'status' => $log->action === 'success' ? 'success' : ($log->action === 'denied' ? 'denied' : ($log->action ?? 'unknown')),
                    'device' => $log->device,
                    'key_name' => $log->key_name,
                    'accessed_item' => $log->key_name ?? $log->device ?? 'Key Box Access',
                    'timestamp' => $createdAt ? $createdAt->format('M j, Y H:i') : '',
                    'time_ago' => $createdAt ? $createdAt->diffForHumans() : '',
                ];
            });
        
        // Key Transactions (Last 5)
        $recentKeyTransactions = KeyTransaction::with('labKey')
            ->orderBy('transaction_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'key_name' => $transaction->labKey->key_name ?? 'Unknown Key',
                    'user_name' => $transaction->user_name,
                    'action' => $transaction->action,
                    'transaction_time' => $transaction->transaction_time->format('M j, Y H:i'),
                    'time_ago' => $transaction->transaction_time->diffForHumans(),
                ];
            });
        
        // Weekly Access Chart Data
        $weeklyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = AccessLog::where('action', 'door_unlocked')
                ->whereDate('created_at', $date)
                ->count();
            $weeklyData[] = [
                'date' => $date->format('M j'),
                'count' => $count
            ];
        }
        
        // Access by Type
        $accessByType = [
            'rfid' => AccessLog::where('type', 'rfid')->count(),
            'fingerprint' => AccessLog::where('type', 'fingerprint')->count(),
            'remote' => AccessLog::where('type', 'remote')->count(),
        ];
        
        // Success Rate
        $successRate = $totalAccess > 0 ? round(($successfulAccess / $totalAccess) * 100, 1) : 0;

        // Latest Key Taken
        $latestKeyTaken = KeyTransaction::with('labKey')
            ->where('action', 'checkout')
            ->latest('transaction_time')
            ->first();

        // Latest Security Snap - look for photos in access logs or create placeholder
        $latestSecuritySnap = AccessLog::whereNotNull('photo_url')
            ->latest('created_at')
            ->first();

        // If no real security snap, create a demo one for testing
        if (!$latestSecuritySnap) {
            $latestSecuritySnap = (object)[
                'id' => 1,
                'photo_url' => '/images/security-camera-placeholder.jpg', // Make sure this image exists
                'created_at' => now(),
            ];
        }

        return Inertia::render('overview', [
            'latestKeyTaken' => $latestKeyTaken ? [
                'id' => $latestKeyTaken->id,
                'key_name' => $latestKeyTaken->labKey->key_name ?? 'Unknown Key',
                'user_name' => $latestKeyTaken->user_name,
                'time' => $latestKeyTaken->transaction_time->format('M j, Y H:i'),
                'time_ago' => $latestKeyTaken->transaction_time->diffForHumans(),
            ] : null,
            'latestSecuritySnap' => $latestSecuritySnap ? [
                'id' => $latestSecuritySnap->id,
                'url' => $latestSecuritySnap->photo_url,
                'time' => $latestSecuritySnap->created_at->format('M j, Y H:i'),
                'time_ago' => $latestSecuritySnap->created_at->diffForHumans(),
            ] : null,
            'keyBox' => [
                'status' => $keyBox->status ?? 'offline',
                'location' => $keyBox->location ?? 'Unknown',
                'last_seen' => $keyBox->last_seen ? $keyBox->last_seen->diffForHumans() : 'Never',
                'ip_address' => $keyBox->ip_address ?? 'Unknown',
                'wifi_strength' => $keyBox->wifi_strength ?? null,
                'uptime' => $keyBox->formatted_uptime ?? 'Unknown',
                'camera_status' => $cameraDevice->status ?? 'offline',
            ],
            'cameraStatus' => $cameraDevice->status ?? 'offline',
            'stats' => [
                'keys' => [
                    'total' => $totalKeys,
                    'available' => $availableKeys,
                    'checked_out' => $checkedOutKeys,
                ],
                'users' => [
                    'total' => $totalUsers,
                    'iot_enabled' => $iotUsers,
                    'active' => $activeUsers,
                ],
                'access' => [
                    'total' => $totalAccess,
                    'successful' => $successfulAccess,
                    'failed' => $failedAccess,
                    'today' => $todayAccess,
                    'success_rate' => $successRate,
                ],
                'access_by_type' => $accessByType,
            ],
            'recentActivity' => $recentActivity,
            'recentKeyTransactions' => $recentKeyTransactions,
            'weeklyData' => $weeklyData,
        ]);
    }
}