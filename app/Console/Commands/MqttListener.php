<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;
use App\Models\AccessLog;
use App\Models\LabKey;
use App\Models\KeyTransaction;
use App\Models\IoTDevice;
use App\Models\User;

class MqttListener extends Command
{
    protected $signature = 'mqtt:listen {--timeout=0 : Stop after N seconds (0 = forever)}';
    protected $description = 'Connect to the configured MQTT broker and log incoming messages.';

    public function handle()
    {
        $config = config('services.mqtt');

        $host = (string)($config['host'] ?? '127.0.0.1');
        $port = (int)($config['port'] ?? 1883);
        $username = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        $clientId = (string)($config['client_id'] ?? 'intellilock-dashboard');
        $topicPrefix = (string)($config['topic_prefix'] ?? 'intellilock');

        $useTls = (bool)($config['tls'] ?? false);
        $timeout = (int)$this->option('timeout');

        $this->info(sprintf('MQTT connecting to %s:%d as %s (TLS: %s)', $host, $port, $clientId, $useTls ? 'on' : 'off'));

        $settings = (new ConnectionSettings())
            ->setUsername($username ?: null)
            ->setPassword($password ?: null)
            ->setUseTls($useTls)
            ->setTlsVerifyPeer(false)
            ->setKeepAliveInterval(60);

        $client = new MqttClient($host, $port, $clientId);

        try {
            $client->connect($settings, true);
            $this->info('✅ MQTT connected successfully');
        } catch (\Throwable $e) {
            $this->error('❌ MQTT connection failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $topicWildcard = rtrim($topicPrefix, '/') . '/#';
        $eventsTopic = rtrim($topicPrefix, '/') . '/events';
        
        $this->info('📥 Subscribed to: ' . $topicWildcard);
        $this->info('👂 Listening for MQTT messages...');

        // Subscribe to wildcard
        $client->subscribe($topicWildcard, function (string $topic, string $message) use ($topicPrefix, $eventsTopic, $client) {
            if ($topic === $eventsTopic) {
                return; // Skip, handled separately
            }

            $base = rtrim($topicPrefix, '/');
            $suffix = ltrim(substr($topic, strlen($base)), '/');

            $this->info(sprintf('📨 Topic: %s', $suffix));

            $data = null;
            try {
                $decoded = json_decode($message, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\Throwable $e) {
                Log::warning('MQTT payload parse failed: ' . $e->getMessage());
            }

            try {
                // ==================== AUTHORIZATION CHECK ====================
                if ($suffix === 'auth/check') {
                    $this->info('🎯 AUTH CHECK RECEIVED');
                    $uid = strtoupper((string)($data['uid'] ?? ''));
                    $authType = (string)($data['auth_type'] ?? 'rfid_check');
                    $device = (string)($data['device'] ?? 'main_controller');
                    $mode = (string)($data['mode'] ?? 'IDLE');
                    
                    $this->info(sprintf('🔑 Checking UID: %s (Mode: %s)', $uid, $mode));
                    
                    // Check both user and key tables
                    $user = User::where('rfid_uid', $uid)->first();
                    $labKey = LabKey::where('key_rfid_uid', $uid)->first();
                    
                    // DEBUG: Log what we found
                    if ($labKey) {
                        $this->info(sprintf('✅ Found LabKey: %s with UID: %s', $labKey->key_name, $labKey->key_rfid_uid));
                    } else {
                        $this->warn(sprintf('❌ No LabKey found with UID: %s', $uid));
                    }
                    
                    // FIX: For RETURN mode, always allow known keys through
                    if ($mode === 'RETURN' && $labKey) {
                        $this->info(sprintf('🔄 KEY RETURN DETECTED: %s', $uid));
                        
                        // Process key return with proper user resolution
                        $userData = $data['user'] ?? null;
                        $this->processKeyReturn($labKey, $device, $userData);
                        
                        $authorized = true;
                        $userName = $labKey->key_name;
                        $userType = 'key_return';
                    }
                    // FIX: For RETURN mode with unknown UID, still allow but mark as unknown
                    elseif ($mode === 'RETURN') {
                        $this->warn(sprintf('🔄 RETURN mode but key not found: %s', $uid));
                        $authorized = true;
                        $userName = 'Unknown Key';
                        $userType = 'key_unknown';
                        
                        // Auto-create the key if it doesn't exist and process return
                        $labKey = $this->autoCreateKey($uid, $device);
                        $userData = $data['user'] ?? null;
                        $this->processKeyReturn($labKey, $device, $userData);
                    }
                    elseif ($user) {
                        // It's a USER card
                        $authorized = true;
                        $userName = $user->name;
                        $userType = 'user';
                        $this->info(sprintf('✅ User found: %s (%s)', $userName, $uid));
                    } elseif ($labKey) {
                        // It's a KEY tag
                        $authorized = true;
                        $userName = $labKey->key_name;
                        $userType = 'key';
                        $this->info(sprintf('✅ Key found: %s (%s)', $labKey->key_name, $uid));
                    } else {
                        // NOT FOUND
                        $authorized = false;
                        $userName = 'Unauthorized';
                        $userType = 'unknown';
                        $this->warn(sprintf('❌ UID not found: %s', $uid));
                    }
                    
                    // Send response back to ESP32
                    $response = [
                        'uid' => $uid,
                        'authorized' => $authorized,
                        'user_name' => $userName,
                        'user_type' => $userType,
                        'timestamp' => now()->timestamp
                    ];
                    
                    $responseJson = json_encode($response);
                    $responseTopic = rtrim($topicPrefix, '/') . '/auth/response';
                    
                    $client->publish($responseTopic, $responseJson);
                    $this->info(sprintf('📤 Auth Response: %s -> %s (%s)', 
                        $uid, $authorized ? 'Authorized' : 'Denied', $userType));
                }

                // ==================== KEY DETECTION (TAKE KEY) ====================
                elseif ($suffix === 'key/detected') {
                    $this->info('🔑 KEY TAKE EVENT');
                    $keyUID = strtoupper((string)($data['key_uid'] ?? ''));
                    $userData = $data['user'] ?? null;
                    $device = (string)($data['device'] ?? 'main_controller');
                    
                    $this->info(sprintf('🔑 Taking Key: %s by user: %s', $keyUID, $userData ?? 'Unknown'));
                    
                    $this->processKeyTake($keyUID, $userData, $device);
                }

                // ==================== KEY RETURN ====================
                elseif ($suffix === 'key/return') {
                    $this->info('🔑 KEY RETURN EVENT');
                    $keyUID = strtoupper((string)($data['key_uid'] ?? ''));
                    $userData = $data['user'] ?? null;
                    $device = (string)($data['device'] ?? 'main_controller');
                    
                    $this->info(sprintf('🔑 Returning Key: %s', $keyUID));
                    
                    $this->processKeyReturnByUid($keyUID, $userData, $device);
                }

                // ==================== DEVICE STATUS ====================
                elseif ($suffix === 'status') {
                    if (!is_array($data)) return;
                    
                    $terminal = (string)($data['device'] ?? 'main_controller');
                    $status = (string)($data['status'] ?? 'online');
                    
                    // Determine device type based on terminal name or device_type from data
                    $deviceType = $data['device_type'] ?? '';
                    
                    // If device_type not provided in data, determine it from terminal name
                    if (empty($deviceType)) {
                        $terminalLower = strtolower($terminal);
                        if ($terminalLower === 'main_controller' || $terminalLower === 'esp32') {
                            $deviceType = 'esp32';
                        } elseif (str_contains($terminalLower, 'camera') || str_contains($terminalLower, 'cam')) {
                            $deviceType = 'esp32-cam';
                        } elseif (str_contains($terminalLower, 'lcd') || str_contains($terminalLower, 'display')) {
                            $deviceType = 'lcd';
                        } elseif (str_contains($terminalLower, 'fingerprint') || str_contains($terminalLower, 'finger')) {
                            $deviceType = 'fingerprint';
                        } elseif (str_contains($terminalLower, 'rfid') || str_contains($terminalLower, 'reader')) {
                            $deviceType = 'rfid';
                        } else {
                            $deviceType = 'esp32'; // default to esp32
                        }
                    }
                    
                    // Use updateOrCreate to ensure device exists
                    $device = IoTDevice::updateOrCreate(
                        ['terminal_name' => $terminal],
                        [
                            'device_type' => $deviceType,
                            'status' => in_array($status, ['online', 'offline', 'error']) ? $status : 'online',
                            'ip_address' => $data['ip'] ?? $data['ip_address'] ?? null,
                            'wifi_strength' => $data['wifi_rssi'] ?? $data['wifi_strength'] ?? null,
                            'uptime' => $data['uptime'] ?? null,
                            'free_memory' => $data['free_heap'] ?? $data['free_memory'] ?? null,
                            'last_seen' => now(),
                        ]
                    );
                    
                    $this->info(sprintf('📊 Device status updated: %s -> %s (Type: %s)', $terminal, $status, $deviceType));
                }

                // ==================== CAMERA RESULT ====================
                elseif ($suffix === 'camera/result') {
                    $this->info('📷 Camera result logged');
                    $log = new AccessLog();
                    $log->action = 'camera_result';
                    $log->device = 'camera';
                    $log->save();
                }
                
            } catch (\Throwable $e) {
                $this->error('❌ Error: ' . $e->getMessage());
                Log::error('MQTT error: ' . $e->getMessage(), [
                    'topic' => $topic,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }, 0);
        
        // Subscribe to events topic
        $client->subscribe($eventsTopic, function (string $topic, string $message) {
            $data = json_decode($message, true);
            if (!is_array($data)) return;
            
            $action = (string)($data['action'] ?? 'unknown');
            
            // Skip key events (handled by specific topics)
            if (in_array($action, ['key_taken', 'key_returned'])) {
                return;
            }
            
            $userData = $data['user'] ?? null;
            $device = (string)($data['device'] ?? 'main_controller');
            $keyInfo = $data['key_info'] ?? null;
            
            // Resolve user name properly
            $userName = $this->resolveUserName($userData);
            
            // Check for duplicates
            $recentDuplicate = AccessLog::where('action', $action)
                ->where('user', $userName)
                ->where('created_at', '>=', now()->subSeconds(5))
                ->exists();
            
            if ($recentDuplicate) {
                return;
            }
            
            // Create log (without status field)
            $log = new AccessLog();
            $log->action = $action;
            $log->user = $userName;
            $log->key_name = $keyInfo;
            $log->device = $device;
            $log->save();
            
            $this->info(sprintf('✅ Event: %s by %s', $action, $userName));
        }, 1);

        $start = microtime(true);
        while (true) {
            $client->loop(true);

            if ($timeout > 0 && (microtime(true) - $start) >= $timeout) {
                break;
            }
        }

        try {
            $client->disconnect();
        } catch (\Throwable $e) {
            // Ignore
        }

        return self::SUCCESS;
    }

    /**
     * Process key take operation
     */
    private function processKeyTake(string $keyUID, $userData, string $device): void
    {
        // Check for duplicates
        $recentDuplicate = AccessLog::where('action', 'key_taken')
            ->where('key_name', $keyUID)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();
        
        if ($recentDuplicate) {
            $this->line('⏭️  Duplicate key detection ignored');
            return;
        }
        
        // Find or create LabKey
        $labKey = LabKey::where('key_rfid_uid', $keyUID)->first();
        
        if (!$labKey) {
            $this->warn(sprintf('⚠️  Key %s not in database - creating placeholder', $keyUID));
            $labKey = new LabKey();
            $labKey->key_name = 'Auto-Key-' . substr($keyUID, -6);
            $labKey->key_rfid_uid = $keyUID;
            $labKey->status = 'available';
            $labKey->is_active = true;
            $labKey->location = 'Auto-registered via MQTT';
            $labKey->save();
            
            $this->info(sprintf('✅ Created new key: %s', $labKey->key_name));
        } else {
            $this->info(sprintf('✅ Found existing key: %s (Status: %s)', 
                $labKey->key_name, $labKey->status));
        }
        
        // Resolve user name properly
        $userName = $this->resolveUserName($userData);
        
        // Use the actual key name from database
        $keyNameForLog = $labKey->key_name;
        
        // Create access log (without status field)
        $log = new AccessLog();
        $log->action = 'key_taken';
        $log->user = $userName;
        $log->key_name = $keyNameForLog;
        $log->device = $device;
        $log->save();
        
        // Get user for transaction
        $user = is_string($userData) && !empty($userData) 
            ? User::where('rfid_uid', strtoupper($userData))->first() 
            : null;

        // Create transaction
        $transaction = new KeyTransaction();
        $transaction->lab_key_id = $labKey->id;
        $transaction->user_name = $userName;
        $transaction->user_rfid_uid = $user ? $user->rfid_uid : (is_string($userData) ? strtoupper($userData) : null);
        $transaction->user_fingerprint_id = $user ? $user->fingerprint_id : null;
        $transaction->action = 'checkout';
        $transaction->transaction_time = now();
        $transaction->device = $device;
        $transaction->notes = 'Key taken via RFID scan';
        $transaction->save();

        // Update status
        $labKey->status = 'checked_out';
        $labKey->last_used_at = now();
        $labKey->save();
        
        $this->info(sprintf('✅ Key %s checked out by %s', $keyNameForLog, $userName));
    }

    /**
     * Process key return by UID
     */
    private function processKeyReturnByUid(string $keyUID, $userData, string $device): void
    {
        $labKey = LabKey::where('key_rfid_uid', $keyUID)->first();
        
        if (!$labKey) {
            $this->error(sprintf('❌ Key %s not found for return', $keyUID));
            
            // Auto-create the key if it doesn't exist
            $labKey = $this->autoCreateKey($keyUID, $device);
        }
        
        $this->processKeyReturn($labKey, $device, $userData);
    }

    /**
     * Process key return operation
     */
    private function processKeyReturn(LabKey $labKey, string $device, $userData = null): void
    {
        // Use the actual key name from database
        $keyNameForLog = $labKey->key_name;
        
        // Check for duplicates
        $recentDuplicate = AccessLog::where('action', 'key_returned')
            ->where('key_name', $keyNameForLog)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();
        
        if ($recentDuplicate) {
            $this->line('⏭️  Duplicate key return ignored');
            return;
        }
        
        // Find last checkout transaction to get the actual user who took the key
        $lastTransaction = KeyTransaction::where('lab_key_id', $labKey->id)
            ->where('action', 'checkout')
            ->orderBy('transaction_time', 'desc')
            ->first();
        
        // Resolve user name - prioritize the actual user who checked out the key
        $userName = 'AUTO_RETURN'; // Default fallback
        
        if ($lastTransaction) {
            // Use the user from the last checkout transaction
            $userName = $lastTransaction->user_name;
            $this->info(sprintf('🔍 Found original user: %s from transaction', $userName));
        } else {
            // Fallback to provided user data if no transaction found
            $resolvedUser = $this->resolveUserName($userData);
            if ($resolvedUser !== 'Unknown User') {
                $userName = $resolvedUser;
            }
            $this->warn(sprintf('⚠️  No checkout transaction found for key %s, using: %s', $keyNameForLog, $userName));
        }
        
        // Create access log (without status field)
        $log = new AccessLog();
        $log->action = 'key_returned';
        $log->user = $userName;
        $log->key_name = $keyNameForLog;
        $log->device = $device;
        $log->save();
        
        // Create transaction
        $transaction = new KeyTransaction();
        $transaction->lab_key_id = $labKey->id;
        $transaction->user_name = $userName;
        $transaction->user_rfid_uid = $lastTransaction ? $lastTransaction->user_rfid_uid : null;
        $transaction->user_fingerprint_id = $lastTransaction ? $lastTransaction->user_fingerprint_id : null;
        $transaction->action = 'checkin';
        $transaction->transaction_time = now();
        $transaction->device = $device;
        $transaction->notes = 'Key returned via RFID scan';
        $transaction->save();

        // Update status
        $labKey->status = 'available';
        $labKey->last_used_at = now();
        $labKey->save();
        
        $this->info(sprintf('✅ Key %s checked in by %s', $keyNameForLog, $userName));
    }

    /**
     * Auto-create key if not found
     */
    private function autoCreateKey(string $uid, string $device): LabKey
    {
        $labKey = LabKey::where('key_rfid_uid', $uid)->first();
        
        if (!$labKey) {
            $this->warn(sprintf('⚠️  Auto-creating key: %s', $uid));
            $labKey = new LabKey();
            $labKey->key_name = 'Auto-Key-' . substr($uid, -6);
            $labKey->key_rfid_uid = $uid;
            $labKey->status = 'checked_out';
            $labKey->is_active = true;
            $labKey->location = 'Auto-registered via Return';
            $labKey->save();
            
            $this->info(sprintf('✅ Created new key: %s', $labKey->key_name));
        }
        
        return $labKey;
    }

    /**
     * Resolve user name from user data
     */
    private function resolveUserName($userData): string
    {
        if (is_string($userData) && !empty($userData)) {
            $user = User::where('rfid_uid', strtoupper($userData))->first();
            return $user ? $user->name : $userData;
        }
        
        return 'Unknown User';
    }
}