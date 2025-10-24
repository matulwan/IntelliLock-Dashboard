<?php

namespace Database\Seeders;

use App\Models\AccessLog;
use App\Models\LabKey;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AccessLogWithKeysSeeder extends Seeder
{
    public function run(): void
    {
        // Get some lab keys
        $labKeys = LabKey::all();
        
        $accessLogs = [
            // Key box access (no specific key)
            [
                'user' => 'John Doe',
                'type' => 'rfid',
                'timestamp' => now()->subMinutes(5),
                'status' => 'success',
                'role' => 'admin',
                'device' => 'lab_key_box',
                'lab_key_id' => null,
                'key_name' => null
            ],
            // Specific key access
            [
                'user' => 'Jane Smith',
                'type' => 'fingerprint',
                'timestamp' => now()->subMinutes(10),
                'status' => 'success',
                'role' => 'user',
                'device' => 'lab_key_box',
                'lab_key_id' => $labKeys->first()?->id,
                'key_name' => $labKeys->first()?->key_name
            ],
            [
                'user' => 'Mike Johnson',
                'type' => 'rfid',
                'timestamp' => now()->subMinutes(15),
                'status' => 'success',
                'role' => 'user',
                'device' => 'lab_key_box',
                'lab_key_id' => $labKeys->skip(1)->first()?->id,
                'key_name' => $labKeys->skip(1)->first()?->key_name
            ],
            // Failed access attempt
            [
                'user' => 'Unknown',
                'type' => 'rfid',
                'timestamp' => now()->subMinutes(20),
                'status' => 'denied',
                'role' => 'guest',
                'device' => 'lab_key_box',
                'lab_key_id' => null,
                'key_name' => null
            ],
            // More key accesses
            [
                'user' => 'Sarah Wilson',
                'type' => 'fingerprint',
                'timestamp' => now()->subHours(1),
                'status' => 'success',
                'role' => 'user',
                'device' => 'lab_key_box',
                'lab_key_id' => $labKeys->skip(2)->first()?->id,
                'key_name' => $labKeys->skip(2)->first()?->key_name
            ],
            [
                'user' => 'Bob Brown',
                'type' => 'rfid',
                'timestamp' => now()->subHours(2),
                'status' => 'success',
                'role' => 'user',
                'device' => 'lab_key_box',
                'lab_key_id' => $labKeys->skip(3)->first()?->id,
                'key_name' => $labKeys->skip(3)->first()?->key_name
            ],
            [
                'user' => 'Alice Green',
                'type' => 'fingerprint',
                'timestamp' => now()->subHours(3),
                'status' => 'success',
                'role' => 'user',
                'device' => 'lab_key_box',
                'lab_key_id' => $labKeys->skip(4)->first()?->id,
                'key_name' => $labKeys->skip(4)->first()?->key_name
            ]
        ];

        foreach ($accessLogs as $log) {
            AccessLog::create($log);
        }
    }
}
