<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IoTDevice;
use Inertia\Inertia; // Add this import
use Illuminate\Support\Facades\Schema; // Add this for table existence check

class DeviceController extends Controller
{
    /**
     * Display devices dashboard
     */
    public function dashboard()
    {
        // Check if table exists first
        if (!Schema::hasTable('iot_devices')) {
            return Inertia::render('Devices', [
                'devices' => [],
                'stats' => [
                    'totalDevices' => 0,
                    'onlineDevices' => 0,
                    'offlineDevices' => 0,
                    'errorDevices' => 0,
                    'averageUptime' => '0 hours',
                    'systemHealth' => 0
                ],
                'lastUpdate' => now()->format('Y-m-d H:i:s'),
                'setupRequired' => true
            ]);
        }

        $devices = IoTDevice::orderBy('last_seen', 'desc')->get();
        
        $stats = [
            'totalDevices' => $devices->count(),
            'onlineDevices' => $devices->where('status', 'online')->count(),
            'offlineDevices' => $devices->where('status', 'offline')->count(),
            'errorDevices' => $devices->where('status', 'error')->count(),
            'averageUptime' => $this->calculateAverageUptime($devices),
            'systemHealth' => $this->calculateSystemHealth($devices)
        ];

        return Inertia::render('Devices', [
            'devices' => $devices,
            'stats' => $stats,
            'lastUpdate' => now()->format('Y-m-d H:i:s'),
            'setupRequired' => false
        ]);
    }

    /**
     * List all devices with status (API)
     */
    public function index()
    {
        if (!Schema::hasTable('iot_devices')) {
            return response()->json(['error' => 'Devices table not set up'], 503);
        }

        $devices = IoTDevice::orderBy('last_seen', 'desc')->get();
        return response()->json($devices);
    }

    /**
     * Show a single device
     */
    public function show($id)
    {
        if (!Schema::hasTable('iot_devices')) {
            return response()->json(['error' => 'Devices table not set up'], 503);
        }

        $device = IoTDevice::find($id);
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }
        return response()->json($device);
    }

    /**
     * Update device status manually from dashboard
     */
    public function update(Request $request, $id)
    {
        if (!Schema::hasTable('iot_devices')) {
            return response()->json(['error' => 'Devices table not set up'], 503);
        }

        $device = IoTDevice::find($id);
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $device->status = $request->input('status', $device->status);
        $device->ip_address = $request->input('ip_address', $device->ip_address);
        $device->wifi_strength = $request->input('wifi_strength', $device->wifi_strength);
        $device->uptime = $request->input('uptime', $device->uptime);
        $device->free_memory = $request->input('free_memory', $device->free_memory);
        $device->last_seen = now();

        $device->save();

        return response()->json(['message' => 'Device updated successfully', 'device' => $device]);
    }

    /**
     * Calculate average uptime from devices
     */
    private function calculateAverageUptime($devices)
    {
        $onlineDevices = $devices->where('status', 'online');
        if ($onlineDevices->count() === 0) {
            return '0 hours';
        }

        $totalUptime = 0;
        $count = 0;

        foreach ($onlineDevices as $device) {
            if ($device->uptime) {
                $totalUptime += $this->parseUptimeToSeconds($device->uptime);
                $count++;
            }
        }

        if ($count === 0) {
            return '0 hours';
        }

        $averageSeconds = $totalUptime / $count;
        return $this->formatUptime($averageSeconds);
    }

    /**
     * Calculate system health percentage
     */
    private function calculateSystemHealth($devices)
    {
        if ($devices->count() === 0) {
            return 100;
        }

        $onlineCount = $devices->where('status', 'online')->count();
        return round(($onlineCount / $devices->count()) * 100);
    }

    /**
     * Parse uptime string to seconds
     */
    private function parseUptimeToSeconds($uptime)
    {
        // Handle numeric uptime (milliseconds from ESP32)
        if (is_numeric($uptime)) {
            $seconds = intval($uptime) / 1000; // Convert ms to seconds
            return $seconds > 0 ? $seconds : 0;
        }

        // Parse string formats like "5 days, 12 hours" or "2 days, 8 hours"
        if (is_string($uptime)) {
            preg_match('/(\d+)\s*days?[,\s]*(\d+)\s*hours?/', $uptime, $matches);
            if (count($matches) >= 3) {
                $days = intval($matches[1]);
                $hours = intval($matches[2]);
                return ($days * 24 * 3600) + ($hours * 3600);
            }

            // Parse hours only
            preg_match('/(\d+)\s*hours?/', $uptime, $matches);
            if (count($matches) >= 2) {
                return intval($matches[1]) * 3600;
            }

            // Parse minutes
            preg_match('/(\d+)\s*minutes?/', $uptime, $matches);
            if (count($matches) >= 2) {
                return intval($matches[1]) * 60;
            }
        }

        return 0;
    }

    /**
     * Format seconds to human readable uptime
     */
    private function formatUptime($seconds)
    {
        $days = floor($seconds / (24 * 3600));
        $hours = floor(($seconds % (24 * 3600)) / 3600);
        
        if ($days > 0) {
            return $days . ' day' . ($days > 1 ? 's' : '') . ', ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        if ($hours > 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        $minutes = floor($seconds / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
}