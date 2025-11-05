<?php

namespace App\Http\Controllers;

use App\Models\LabKey;
use App\Models\KeyTransaction;
use App\Models\IoTDevice;
use App\Models\SystemAlert;
use Inertia\Inertia;
use Inertia\Response;

class KeyManagementController extends Controller
{
    /**
     * Display the key management page with real data
     */
    public function index(): Response
    {
        $device = 'lab_key_box';
        
        // Get device status
        $deviceInfo = IoTDevice::where('terminal_name', $device)->first();
        
        // Get all keys with their current holder
        $labKeys = LabKey::where('is_active', true)
            ->with(['latestTransaction' => function ($query) {
                $query->latest('transaction_time');
            }])
            ->get()
            ->map(function ($key) {
                $holder = $key->getCurrentHolder();
                
                return [
                    'id' => $key->id,
                    'name' => $key->key_name,
                    'description' => $key->description ?? 'Lab Key',
                    'status' => $key->status,
                    'rfid' => $key->key_rfid_uid,
                    'holder' => $holder,
                    'location' => $key->location ?? 'Key Box'
                ];
            });
        
        // Calculate statistics
        $totalKeys = $labKeys->count();
        $availableKeys = $labKeys->where('status', 'available')->count();
        $checkedOutKeys = $labKeys->where('status', 'checked_out')->count();
        
        // Get recent transactions
        $recentTransactions = KeyTransaction::with('labKey')
            ->where('device', $device)
            ->latest('transaction_time')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'key_name' => $transaction->labKey ? $transaction->labKey->key_name : 'Unknown Key',
                    'user_name' => $transaction->user_name,
                    'action' => $transaction->action,
                    'time' => $transaction->transaction_time->diffForHumans(),
                    'formatted_time' => $transaction->transaction_time->format('M j, Y g:i A')
                ];
            });
        
        // Get active alerts
        $activeAlerts = SystemAlert::where('device', $device)
            ->where('status', 'active')
            ->latest('alert_time')
            ->limit(5)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'type' => $alert->alert_type,
                    'severity' => $alert->severity,
                    'title' => $alert->title,
                    'description' => $alert->description,
                    'time' => $alert->alert_time->diffForHumans()
                ];
            });
        
        // Prepare key box status
        $keyBoxStatus = [
            'status' => $deviceInfo ? ($deviceInfo->status ?? 'offline') : 'offline',
            'location' => 'Main Lab Office',
            'last_seen' => ($deviceInfo && $deviceInfo->last_seen) ? $deviceInfo->last_seen->diffForHumans() : 'Never',
            'ip_address' => $deviceInfo ? ($deviceInfo->ip_address ?? 'Unknown') : 'Unknown',
            'totalKeys' => $totalKeys,
            'availableKeys' => $availableKeys,
            'checkedOutKeys' => $checkedOutKeys
        ];
        
        return Inertia::render('key-management', [
            'keyBoxStatus' => $keyBoxStatus,
            'labKeys' => $labKeys,
            'recentTransactions' => $recentTransactions,
            'activeAlerts' => $activeAlerts
        ]);
    }
}
