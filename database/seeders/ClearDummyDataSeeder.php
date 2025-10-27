<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\AccessLog;
use App\Models\KeyTransaction;
use App\Models\LabKey;
use App\Models\EventPhoto;
use App\Models\SystemAlert;

class ClearDummyDataSeeder extends Seeder
{
    /**
     * Clear all dummy/test data from the database
     * This will remove all data except admin users
     * Run: php artisan db:seed --class=ClearDummyDataSeeder
     */
    public function run(): void
    {
        $this->command->info('🧹 Clearing dummy data from database...');
        
        // Clear event photos (with file deletion)
        $this->command->info('Deleting event photos...');
        $photos = EventPhoto::all();
        foreach ($photos as $photo) {
            $photo->delete(); // This will trigger the model's delete event to remove files
        }
        
        // Clear system alerts
        $this->command->info('Deleting system alerts...');
        SystemAlert::truncate();
        
        // Clear key transactions
        $this->command->info('Deleting key transactions...');
        KeyTransaction::truncate();
        
        // Clear access logs
        $this->command->info('Deleting access logs...');
        AccessLog::truncate();
        
        // Clear lab keys
        $this->command->info('Deleting lab keys...');
        LabKey::truncate();
        
        // Reset IoT device status (keep the device but reset stats)
        $this->command->info('Resetting IoT device status...');
        DB::table('iot_devices')->update([
            'status' => 'offline',
            'last_seen' => null,
            'uptime' => 0,
            'free_memory' => null,
            'wifi_strength' => null,
        ]);
        
        $this->command->info('✅ All dummy data cleared!');
        $this->command->info('📊 Database is now ready for real ESP32 data.');
        $this->command->info('');
        $this->command->info('Next steps:');
        $this->command->info('1. Start Laravel server: php artisan serve --host=0.0.0.0 --port=8000');
        $this->command->info('2. Update ESP32 WiFi and API URL');
        $this->command->info('3. Upload ESP32 code and test!');
    }
}
