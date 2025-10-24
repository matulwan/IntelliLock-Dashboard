<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UpdateUsersForIoTSeeder extends Seeder
{
    public function run(): void
    {
        // Update existing users with IoT access data
        $users = User::limit(5)->get();
        
        $iotData = [
            [
                'rfid_uid' => '14B13C03',
                'fingerprint_id' => 1,
                'role' => 'admin',
                'iot_access' => true,
                'notes' => 'System administrator with full access'
            ],
            [
                'rfid_uid' => '30018B15',
                'fingerprint_id' => 2,
                'role' => 'user',
                'iot_access' => true,
                'notes' => 'Regular user with standard access'
            ],
            [
                'rfid_uid' => 'A1B2C3D4',
                'fingerprint_id' => null,
                'role' => 'user',
                'iot_access' => true,
                'notes' => 'RFID only access'
            ],
            [
                'rfid_uid' => null,
                'fingerprint_id' => 3,
                'role' => 'guest',
                'iot_access' => true,
                'notes' => 'Fingerprint only access'
            ],
            [
                'rfid_uid' => 'E5F6G7H8',
                'fingerprint_id' => 4,
                'role' => 'user',
                'iot_access' => false,
                'notes' => 'IoT access disabled'
            ]
        ];

        foreach ($users as $index => $user) {
            if (isset($iotData[$index])) {
                $user->update($iotData[$index]);
            }
        }
    }
}
