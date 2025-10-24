<?php

namespace Database\Seeders;

use App\Models\AccessLog;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AccessLogSeeder extends Seeder
{
    public function run(): void
    {
        $accessLogs = [
            [
                'user' => 'John Doe',
                'type' => 'rfid',
                'timestamp' => now()->subMinutes(5),
                'status' => 'success',
                'role' => 'admin',
                'device' => 'basement'
            ],
            [
                'user' => 'Jane Smith',
                'type' => 'fingerprint',
                'timestamp' => now()->subMinutes(10),
                'status' => 'success',
                'role' => 'user',
                'device' => 'main_entrance'
            ],
            [
                'user' => 'Unknown',
                'type' => 'rfid',
                'timestamp' => now()->subMinutes(15),
                'status' => 'denied',
                'role' => 'guest',
                'device' => 'basement'
            ],
            [
                'user' => 'Mike Johnson',
                'type' => 'rfid',
                'timestamp' => now()->subHours(1),
                'status' => 'success',
                'role' => 'user',
                'device' => 'office_door'
            ],
            [
                'user' => 'Unknown',
                'type' => 'fingerprint',
                'timestamp' => now()->subHours(2),
                'status' => 'denied',
                'role' => 'guest',
                'device' => 'main_entrance'
            ],
            [
                'user' => 'Sarah Wilson',
                'type' => 'fingerprint',
                'timestamp' => now()->subHours(3),
                'status' => 'success',
                'role' => 'guest',
                'device' => 'basement'
            ]
        ];

        foreach ($accessLogs as $log) {
            AccessLog::create($log);
        }
    }
}
