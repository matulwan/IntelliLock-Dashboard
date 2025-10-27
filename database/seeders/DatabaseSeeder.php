<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Only creates essential admin user - NO dummy data
     */
    public function run(): void
    {
        // Create admin user only (for dashboard access)
        if (!User::where('matrix_number', 'admin')->exists()) {
            User::create([
                'name' => 'Administrator',
                'matrix_number' => 'admin',
                'email' => 'admin@intellilock.local',
                'password' => Hash::make('admin123'),
                'role' => 'Admin',
                'iot_access' => false, // Admin doesn't need IoT access
            ]);
            
            $this->command->info('✅ Admin user created');
            $this->command->info('   Email: admin@intellilock.local');
            $this->command->info('   Password: admin123');
        }
        
        $this->command->info('');
        $this->command->info('📊 Database ready for real ESP32 data!');
        $this->command->info('   No dummy data has been created.');
        $this->command->info('');
        $this->command->info('To clear any existing dummy data, run:');
        $this->command->info('   php artisan db:seed --class=ClearDummyDataSeeder');
    }
}
