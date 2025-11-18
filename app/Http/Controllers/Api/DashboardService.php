<?php

namespace App\Http\Controllers\Api;

use App\Events\DashboardDataUpdated;
use App\Models\AccessLog;
use App\Models\EventPhoto;
use App\Models\IoTDevice;
use App\Models\KeyTransaction;
use App\Models\LabKey;
use Carbon\Carbon;

class DashboardService
{
    public static function getDashboardData(): array
    {
        $keyBox = IoTDevice::where('terminal_name', 'lab_key_box')->first();

        // Key Statistics
        $totalKeys = LabKey::count();
        $availableKeys = LabKey::where('status', 'available')->count();
        $checkedOutKeys = LabKey::where('status', 'checked_out')->count();

        // Access Statistics
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

        // Latest Key Taken
        $latestKeyTaken = KeyTransaction::with('labKey')
            ->where('action', 'checkout')
            ->latest('transaction_time')
            ->first();

        // Latest Security Snap
        $latestSecuritySnap = AccessLog::whereNotNull('photo_url')
            ->latest('created_at')
            ->first();

        return [
            'keyBox' => [
                'status' => $keyBox->status ?? 'offline',
                'location' => $keyBox->location ?? 'Unknown',
                'last_seen' => $keyBox->last_seen ? $keyBox->last_seen->diffForHumans() : 'Never',
            ],
            'stats' => [
                'keys' => [
                    'total' => $totalKeys,
                    'available' => $availableKeys,
                    'checked_out' => $checkedOutKeys,
                ],
                'access' => [
                    'total' => $totalAccess,
                    'successful' => $successfulAccess,
                    'failed' => $failedAccess,
                    'today' => $todayAccess,
                ],
            ],
            'latestKeyTaken' => $latestKeyTaken ? [
                'id' => $latestKeyTaken->id,
                'key_name' => $latestKeyTaken->labKey->key_name ?? 'Unknown Key',
                'user_name' => $latestKeyTaken->user_name,
                'time' => $latestKeyTaken->transaction_time->format('M j, Y H:i'),
                'time_ago' => $latestKeyTaken->transaction_time->diffForHumans(),
                'room' => $latestKeyTaken->labKey->key_name ?? 'Unknown Room',
                'number' => $latestKeyTaken->id,
            ] : null,
            'latestSecuritySnap' => $latestSecuritySnap ? [
                'id' => $latestSecuritySnap->id,
                'user' => $latestSecuritySnap->user,
                'url' => $latestSecuritySnap->photo_url,
                'time' => $latestSecuritySnap->created_at->format('M j, Y H:i'),
                'time_ago' => $latestSecuritySnap->created_at->diffForHumans(),
            ] : null,
            'recentActivity' => AccessLog::query()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'user' => $log->user,
                    'type' => 'rfid',
                    'status' => $log->action,
                    'device' => $log->device,
                    'timestamp' => $log->created_at->format('M j, Y H:i'),
                    'time_ago' => $log->created_at->diffForHumans(),
                ]),
            'recentKeyTransactions' => KeyTransaction::with('labKey')
                ->orderBy('transaction_time', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($transaction) => [
                    'id' => $transaction->id,
                    'key_name' => $transaction->labKey->key_name ?? 'Unknown Key',
                    'user_name' => $transaction->user_name,
                    'action' => $transaction->action,
                    'transaction_time' => $transaction->transaction_time->format('M j, Y H:i'),
                    'time_ago' => $transaction->transaction_time->diffForHumans(),
                ]),
            'weeklyData' => collect(range(6, 0))
                ->map(fn($days) => [
                    'date' => now()->subDays($days)->format('M j'),
                    'count' => AccessLog::whereDate('created_at', now()->subDays($days))->count(),
                ])->toArray(),
        ];
    }
}