<?php

// Simple PHP script to test the IoT API endpoints
// Run this with: php test_api.php

$baseUrl = 'http://localhost:8000/api/iot';

// Test authentication endpoint
function testAuthentication($uid, $type = 'rfid') {
    global $baseUrl;
    
    $data = [
        'terminal' => 'basement',
        'type' => $type,
        'uid' => $uid
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/authenticate');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Testing UID: $uid\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    echo "---\n";
}

// Test with authorized UIDs from your seeder
echo "Testing IoT API Authentication...\n\n";

testAuthentication('14B13C03'); // John Doe - should succeed
testAuthentication('30018B15'); // Jane Smith - should succeed  
testAuthentication('A1B2C3D4'); // Mike Johnson - should succeed
testAuthentication('UNKNOWN1'); // Unknown - should fail

echo "Check your access logs page now!\n";
