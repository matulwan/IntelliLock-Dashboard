<?php
/**
 * ============================================================================
 * INTELLI-LOCK API TESTING SCRIPT
 * ============================================================================
 * This script tests all IoT API endpoints to verify they're working correctly
 * Run this script from command line: php test_iot_api.php
 * Or access via browser: http://localhost:8000/test_iot_api.php
 * ============================================================================
 */

// Configuration
$API_BASE = "http://localhost:8000/api/iot";
$DEVICE_NAME = "lab_key_box";

// ANSI color codes for terminal output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[1;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m"; // No Color

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        INTELLI-LOCK API TESTING SCRIPT                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

/**
 * Helper function to make HTTP requests
 */
function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

/**
 * Test result display
 */
function displayTest($testName, $result, $expectedCode = 200) {
    global $GREEN, $RED, $YELLOW, $BLUE, $NC;
    
    $passed = ($result['code'] == $expectedCode && empty($result['error']));
    $status = $passed ? "{$GREEN}✓ PASS{$NC}" : "{$RED}✗ FAIL{$NC}";
    
    echo "\n{$BLUE}TEST:{$NC} {$testName}\n";
    echo "Status: {$status}\n";
    echo "HTTP Code: {$result['code']} (Expected: {$expectedCode})\n";
    
    if (!empty($result['error'])) {
        echo "{$RED}Error: {$result['error']}{$NC}\n";
    }
    
    if ($result['response']) {
        $json = json_decode($result['response'], true);
        if ($json) {
            echo "Response: " . json_encode($json, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Response: {$result['response']}\n";
        }
    }
    
    echo str_repeat("-", 70) . "\n";
    
    return $passed;
}

// Test counters
$totalTests = 0;
$passedTests = 0;

// ============================================================================
// TEST 1: Heartbeat
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[1/8] Testing Heartbeat Endpoint{$NC}\n";

$result = makeRequest("{$API_BASE}/heartbeat", 'POST', [
    'terminal' => $DEVICE_NAME,
    'timestamp' => time()
]);

if (displayTest("Device Heartbeat", $result)) $passedTests++;

// ============================================================================
// TEST 2: RFID Authentication (Success)
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[2/8] Testing RFID Authentication (Success){$NC}\n";

$result = makeRequest("{$API_BASE}/authenticate", 'POST', [
    'terminal' => $DEVICE_NAME,
    'type' => 'rfid',
    'uid' => 'A1B2C3D4' // This should exist in your database
]);

if (displayTest("RFID Authentication", $result)) $passedTests++;

// ============================================================================
// TEST 3: RFID Authentication (Denied)
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[3/8] Testing RFID Authentication (Denied){$NC}\n";

$result = makeRequest("{$API_BASE}/authenticate", 'POST', [
    'terminal' => $DEVICE_NAME,
    'type' => 'rfid',
    'uid' => 'FFFFFFFF' // Non-existent card
]);

if (displayTest("RFID Authentication (Denied)", $result)) $passedTests++;

// ============================================================================
// TEST 4: Key Transaction (Checkout)
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[4/8] Testing Key Transaction (Checkout){$NC}\n";

$result = makeRequest("{$API_BASE}/key-transaction", 'POST', [
    'key_rfid_uid' => 'KEY001',
    'action' => 'checkout',
    'device' => $DEVICE_NAME,
    'user_rfid_uid' => 'A1B2C3D4'
]);

if (displayTest("Key Checkout", $result, 200)) $passedTests++;

// ============================================================================
// TEST 5: Key Transaction (Checkin)
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[5/8] Testing Key Transaction (Checkin){$NC}\n";

$result = makeRequest("{$API_BASE}/key-transaction", 'POST', [
    'key_rfid_uid' => 'KEY001',
    'action' => 'checkin',
    'device' => $DEVICE_NAME,
    'user_rfid_uid' => 'A1B2C3D4'
]);

if (displayTest("Key Checkin", $result, 200)) $passedTests++;

// ============================================================================
// TEST 6: System Alert
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[6/8] Testing System Alert{$NC}\n";

$result = makeRequest("{$API_BASE}/alert", 'POST', [
    'device' => $DEVICE_NAME,
    'alert_type' => 'door_left_open',
    'severity' => 'high',
    'title' => 'Test Alert',
    'description' => 'This is a test alert from API testing script',
    'user_name' => 'Test User'
]);

if (displayTest("System Alert Creation", $result)) $passedTests++;

// ============================================================================
// TEST 7: System Status
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[7/8] Testing System Status{$NC}\n";

$result = makeRequest("{$API_BASE}/status?device={$DEVICE_NAME}", 'GET');

if (displayTest("Get System Status", $result)) $passedTests++;

// ============================================================================
// TEST 8: Device Status Update
// ============================================================================
$totalTests++;
echo "\n{$YELLOW}[8/8] Testing Device Status Update{$NC}\n";

$result = makeRequest("{$API_BASE}/device-status", 'POST', [
    'terminal' => $DEVICE_NAME,
    'status' => 'online',
    'ip_address' => '192.168.1.150',
    'wifi_strength' => -45,
    'uptime' => 3600,
    'free_memory' => 50000
]);

if (displayTest("Device Status Update", $result)) $passedTests++;

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        TEST SUMMARY                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$percentage = ($totalTests > 0) ? round(($passedTests / $totalTests) * 100, 1) : 0;
$summaryColor = ($percentage >= 80) ? $GREEN : (($percentage >= 50) ? $YELLOW : $RED);

echo "Total Tests: {$totalTests}\n";
echo "Passed: {$summaryColor}{$passedTests}{$NC}\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Success Rate: {$summaryColor}{$percentage}%{$NC}\n";
echo "\n";

if ($passedTests === $totalTests) {
    echo "{$GREEN}✓ All tests passed! Your API is working correctly.{$NC}\n";
} else {
    echo "{$YELLOW}⚠ Some tests failed. Please check the errors above.{$NC}\n";
    echo "\nCommon issues:\n";
    echo "1. Make sure Laravel server is running: php artisan serve\n";
    echo "2. Check database connection in .env file\n";
    echo "3. Run migrations: php artisan migrate\n";
    echo "4. Create test users with RFID UIDs in the database\n";
    echo "5. Check Laravel logs: storage/logs/laravel.log\n";
}

echo "\n";
echo "For more information, see SETUP_GUIDE.md\n";
echo "\n";
