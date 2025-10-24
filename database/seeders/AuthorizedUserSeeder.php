<?php

namespace Database\Seeders;

use App\Models\AuthorizedUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuthorizedUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::first(); // Get the first admin user

        $authorizedUsers = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'rfid_uid' => '14B13C03', // Matches your ESP32 code
                'fingerprint_id' => 1,
                'role' => 'admin',
                'is_active' => true,
                'created_by' => $adminUser?->id,
                'notes' => 'System administrator with full access'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'rfid_uid' => '30018B15', // Matches your ESP32 code
                'fingerprint_id' => 2,
                'role' => 'user',
                'is_active' => true,
                'created_by' => $adminUser?->id,
                'notes' => 'Regular user with standard access'
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike@example.com',
                'rfid_uid' => 'A1B2C3D4',
                'fingerprint_id' => null,
                'role' => 'user',
                'is_active' => true,
                'created_by' => $adminUser?->id,
                'notes' => 'RFID only access'
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah@example.com',
                'rfid_uid' => null,
                'fingerprint_id' => 3,
                'role' => 'guest',
                'is_active' => true,
                'created_by' => $adminUser?->id,
                'notes' => 'Fingerprint only access - temporary guest'
            ],
            [
                'name' => 'Bob Brown',
                'email' => 'bob@example.com',
                'rfid_uid' => 'E5F6G7H8',
                'fingerprint_id' => 4,
                'role' => 'user',
                'is_active' => false,
                'created_by' => $adminUser?->id,
                'notes' => 'Inactive user - access suspended'
            ]
        ];

        foreach ($authorizedUsers as $user) {
            AuthorizedUser::create($user);
        }
    }
}
