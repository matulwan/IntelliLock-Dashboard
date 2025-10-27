<?php
/**
 * Quick script to check current database status
 * Run: php check_data_status.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AccessLog;
use App\Models\KeyTransaction;
use App\Models\LabKey;
use App\Models\EventPhoto;
use App\Models\SystemAlert;
use App\Models\User;
use App\Models\IoTDevice;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           INTELLI-LOCK DATABASE STATUS                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Check data counts
$accessLogs = AccessLog::count();
$keyTransactions = KeyTransaction::count();
$labKeys = LabKey::count();
$eventPhotos = EventPhoto::count();
$systemAlerts = SystemAlert::count();
$users = User::count();
$iotDevices = IoTDevice::count();

echo "📊 Current Data Status:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo sprintf("   Users:             %d\n", $users);
echo sprintf("   IoT Devices:       %d\n", $iotDevices);
echo sprintf("   Lab Keys:          %d\n", $labKeys);
echo sprintf("   Access Logs:       %d\n", $accessLogs);
echo sprintf("   Key Transactions:  %d\n", $keyTransactions);
echo sprintf("   Event Photos:      %d\n", $eventPhotos);
echo sprintf("   System Alerts:     %d\n", $systemAlerts);
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Check if data is empty
$isEmpty = ($accessLogs === 0 && $keyTransactions === 0 && $labKeys === 0 && $eventPhotos === 0 && $systemAlerts === 0);

if ($isEmpty) {
    echo "✅ Database is CLEAN - Ready for real ESP32 data!\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Start Laravel: php artisan serve --host=0.0.0.0 --port=8000\n";
    echo "2. Update ESP32 code with WiFi and API URL\n";
    echo "3. Upload ESP32 code and test!\n";
} else {
    echo "⚠️  Database contains data\n";
    echo "\n";
    
    // Show recent activity
    if ($accessLogs > 0) {
        echo "Recent Access Logs:\n";
        $recentLogs = AccessLog::orderBy('timestamp', 'desc')->limit(5)->get();
        foreach ($recentLogs as $log) {
            echo sprintf("   - %s | %s | %s | %s\n", 
                $log->timestamp->format('M j H:i'),
                $log->user,
                $log->type,
                $log->status
            );
        }
        echo "\n";
    }
    
    if ($keyTransactions > 0) {
        echo "Recent Key Transactions:\n";
        $recentTrans = KeyTransaction::orderBy('transaction_time', 'desc')->limit(5)->get();
        foreach ($recentTrans as $trans) {
            echo sprintf("   - %s | %s | %s\n",
                $trans->transaction_time->format('M j H:i'),
                $trans->user_name,
                $trans->action
            );
        }
        echo "\n";
    }
    
    if ($systemAlerts > 0) {
        echo "Active Alerts:\n";
        $activeAlerts = SystemAlert::where('status', 'active')->get();
        foreach ($activeAlerts as $alert) {
            echo sprintf("   - [%s] %s\n", $alert->severity, $alert->title);
        }
        echo "\n";
    }
    
    echo "To clear all data and start fresh, run:\n";
    echo "   php artisan db:seed --class=ClearDummyDataSeeder\n";
}

echo "\n";
echo "Dashboard: http://localhost:8000\n";
echo "API Endpoint: http://localhost:8000/api/intellilock/event\n";
echo "\n";
