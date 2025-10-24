<?php

namespace Database\Seeders;

use App\Models\LabKey;
use Illuminate\Database\Seeder;

class LabKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labKeys = [
            [
                'key_name' => 'Lab A',
                'key_rfid_uid' => 'LABA001',
                'description' => 'Main Laboratory A - Chemistry Lab',
                'status' => 'available',
                'location' => 'key_box',
                'is_active' => true
            ],
            [
                'key_name' => 'Lab B',
                'key_rfid_uid' => 'LABB002',
                'description' => 'Laboratory B - Physics Lab',
                'status' => 'available',
                'location' => 'key_box',
                'is_active' => true
            ],
            [
                'key_name' => 'Lab C',
                'key_rfid_uid' => 'LABC003',
                'description' => 'Laboratory C - Biology Lab',
                'status' => 'available',
                'location' => 'key_box',
                'is_active' => true
            ],
            [
                'key_name' => 'Lab D',
                'key_rfid_uid' => 'LABD004',
                'description' => 'Laboratory D - Computer Lab',
                'status' => 'available',
                'location' => 'key_box',
                'is_active' => true
            ],
            [
                'key_name' => 'Lab E',
                'key_rfid_uid' => 'LABE005',
                'description' => 'Laboratory E - Research Lab',
                'status' => 'available',
                'location' => 'key_box',
                'is_active' => true
            ]
        ];

        foreach ($labKeys as $key) {
            LabKey::create($key);
        }
    }
}
