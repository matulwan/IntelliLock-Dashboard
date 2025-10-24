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
        
        // Access Statistics
        $totalAccess = AccessLog::count();
        $successfulAccess = AccessLog::where('status', 'success')->count();
        $failedAccess = AccessLog::where('status', 'denied')->count();
        $todayAccess = AccessLog::whereDate('timestamp', today())->count();
        
        // Recent Activity (Last 10 access attempts)
        $recentActivity = AccessLog::with('labKey')
            ->orderBy('timestamp', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user,
                    'type' => $log->type,
                    'status' => $log->status,
                    'device' => $log->device,
                    'key_name' => $log->key_name ?? ($log->labKey ? $log->labKey->key_name : null),
                    'accessed_item' => $log->key_name ?? $log->device ?? 'Key Box Access',
                    'timestamp' => $log->timestamp->format('M j, Y H:i'),
                    'time_ago' => $log->timestamp->diffForHumans(),
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
            $count = AccessLog::whereDate('timestamp', $date)->count();
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
        
        return Inertia::render('overview', [
            'keyBox' => [
                'status' => $keyBox->status ?? 'offline',
                'location' => $keyBox->location ?? 'Unknown',
                'last_seen' => $keyBox->last_seen ? $keyBox->last_seen->diffForHumans() : 'Never',
                'ip_address' => $keyBox->ip_address ?? 'Unknown',
                'wifi_strength' => $keyBox->wifi_strength ?? null,
                'uptime' => $keyBox->formatted_uptime ?? 'Unknown',
            ],
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
