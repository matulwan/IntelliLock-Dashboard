<?php

namespace App\Http\Controllers;

use App\Models\IoTDevice;
use App\Models\AuthorizedUser;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IoTDeviceController extends Controller
{
    public function index()
    {
        $devices = IoTDevice::orderBy('terminal_name')->get();
        
        $stats = [
            'total_devices' => $devices->count(),
            'online_devices' => $devices->filter(fn($device) => $device->isOnline())->count(),
            'offline_devices' => $devices->filter(fn($device) => !$device->isOnline())->count(),
        ];

        return Inertia::render('iot-devices', [
            'devices' => $devices,
            'stats' => $stats
        ]);
    }

    public function show(IoTDevice $device)
    {
        return Inertia::render('iot-device-details', [
            'device' => $device
        ]);
    }

    public function controlDoor(Request $request, IoTDevice $device)
    {
        $request->validate([
            'action' => 'required|in:open,close,lock,unlock',
            'duration' => 'nullable|integer|min:1|max:30'
        ]);

        // Here you would typically send a command to the ESP32
        // For now, we'll just log the action
        
        return response()->json([
            'status' => 'success',
            'message' => "Door {$request->action} command sent to {$device->terminal_name}"
        ]);
    }
}
