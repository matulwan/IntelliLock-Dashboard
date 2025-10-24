<?php

namespace Database\Seeders;

use App\Models\IoTDevice;
use Illuminate\Database\Seeder;

class IoTDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $devices = [
            [
                'terminal_name' => 'lab_key_box',
                'device_type' => 'key_management',
                'status' => 'online',
                'ip_address' => '192.168.1.150',
                'wifi_strength' => -45,
                'uptime' => 86400, // 1 day in seconds
                'free_memory' => 245760, // ~240KB
                'last_seen' => now(),
                'location' => 'Main Lab Office',
                'description' => 'Smart key box with 5 lab keys - RFID and fingerprint access'
            ]
        ];

        foreach ($devices as $device) {
            IoTDevice::create($device);
        }
    }
}
