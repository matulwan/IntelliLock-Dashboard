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

class MqttListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen {--timeout=0 : Stop after N seconds (0 = forever)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect to the configured MQTT broker and log incoming messages.';

    /**
     * Execute the console command.
     */
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
        $tlsVerifyPeer = (bool)($config['tls_verify_peer'] ?? false);
        $tlsAllowSelfSigned = (bool)($config['tls_allow_self_signed'] ?? true);
        $tlsCaFile = $config['tls_ca_file'] ?? null;
        $tlsCaPath = $config['tls_ca_path'] ?? null;

        $timeout = (int)$this->option('timeout');

        $this->info(sprintf('MQTT connecting to %s:%d as %s (TLS: %s)', $host, $port, $clientId, $useTls ? 'on' : 'off'));

        $settings = (new ConnectionSettings())
            ->setUsername($username ?: null)
            ->setPassword($password ?: null)
            ->setUseTls($useTls)
            ->setTlsVerifyPeer($tlsVerifyPeer)
            ->setKeepAliveInterval(60);

        $client = new MqttClient($host, $port, $clientId);

        try {
            $client->connect($settings, true);
        } catch (\Throwable $e) {
            $this->error('MQTT connection failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $topicWildcard = rtrim($topicPrefix, '/') . '/#';
        $this->info('Subscribed to: ' . $topicWildcard);

        $client->subscribe($topicWildcard, function (string $topic, string $message) use ($topicPrefix) {
            $now = now()->toDateTimeString();
            $this->line(sprintf('[%s] %s => %s', $now, $topic, $message));

            // Normalize topic
            $base = rtrim($topicPrefix, '/');
            $suffix = ltrim(substr($topic, strlen($base)), '/');

            // Try to parse JSON payload when applicable
            $data = null;
            try {
                $decoded = json_decode($message, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\Throwable $e) {
                // Non-JSON payloads are fine for some topics
                Log::warning('MQTT payload parse failed: ' . $e->getMessage());
            }

            try {
                // Handle events published by ESP32 main controller
                if ($suffix === 'events') {
                    if (!is_array($data)) {
                        return; // Require JSON for events
                    }

                    $action = (string)($data['action'] ?? 'unknown');
                    $user = $data['user'] ?? null; // may be null for unknown
                    $device = (string)($data['device'] ?? 'main_controller');
                    $keyName = null;
                    if (isset($data['key_info'])) {
                        // key_info can be a string (key name) or object {name, uid}
                        if (is_array($data['key_info'])) {
                            $keyName = $data['key_info']['name'] ?? $data['key_info']['keyName'] ?? null;
                        } else {
                            $keyName = (string)$data['key_info'];
                        }
                    }

                    // Always record an access log for visibility on dashboard
                    $log = new AccessLog();
                    $log->action = $action;
                    $log->user = $user;
                    $log->key_name = $keyName;
                    $log->device = $device;
                    $log->save();

                    // Create/Update key transaction & LabKey status for key events
                    if (in_array($action, ['key_taken', 'key_returned'])) {
                        if ($keyName) {
                            $labKey = LabKey::where('key_name', $keyName)->first();
                            if (!$labKey) {
                                // Create a placeholder LabKey if not present
                                $labKey = new LabKey();
                                $labKey->key_name = $keyName;
                                $labKey->status = 'available';
                                $labKey->is_active = true;
                                $labKey->save();
                            }

                            $transaction = new KeyTransaction();
                            $transaction->lab_key_id = $labKey->id;
                            $transaction->user_name = is_array($user) ? ($user['name'] ?? null) : (is_string($user) ? $user : null);
                            $transaction->user_rfid_uid = is_array($user) ? ($user['rfid_uid'] ?? null) : null;
                            $transaction->user_fingerprint_id = is_array($user) ? ($user['fingerprint_id'] ?? null) : null;
                            $transaction->action = $action === 'key_taken' ? 'checkout' : 'checkin';
                            $transaction->transaction_time = now();
                            $transaction->device = $device;
                            $transaction->notes = null;
                            $transaction->save();

                            // Update LabKey availability status
                            $labKey->status = $action === 'key_taken' ? 'checked_out' : 'available';
                            $labKey->save();
                        }
                    }
                }

                // Device status updates
                elseif ($suffix === 'status') {
                    if (!is_array($data)) {
                        return;
                    }

                    $terminal = (string)($data['device'] ?? 'main_controller');
                    $status = (string)($data['status'] ?? 'online');
                    $ip = $data['ip'] ?? $data['ip_address'] ?? null;
                    $wifi = $data['wifi_strength'] ?? ($data['wifi'] ?? ($data['wifi_rssi'] ?? null));
                    $uptime = $data['uptime'] ?? null;
                    $freeMem = $data['free_memory'] ?? ($data['freeMem'] ?? ($data['free_heap'] ?? null));

                    $device = IoTDevice::firstOrNew(['terminal_name' => $terminal]);
                    $device->device_type = $terminal === 'camera' ? 'camera' : 'access_control';
                    $device->status = in_array($status, ['online', 'offline', 'error']) ? $status : 'online';
                    $device->ip_address = is_string($ip) ? $ip : null;
                    $device->wifi_strength = is_numeric($wifi) ? (int)$wifi : null;
                    $device->uptime = is_numeric($uptime) ? (int)$uptime : null;
                    $device->free_memory = is_numeric($freeMem) ? (int)$freeMem : null;
                    $device->last_seen = now();
                    $device->save();
                }

                // Camera specific topics
                elseif ($suffix === 'camera/status') {
                    if (!is_array($data)) {
                        return;
                    }
                    $terminal = 'camera';
                    $status = (string)($data['status'] ?? 'online');
                    $ip = $data['ip'] ?? $data['ip_address'] ?? null;
                    $wifi = $data['wifi_strength'] ?? ($data['wifi'] ?? ($data['wifi_rssi'] ?? null));
                    $uptime = $data['uptime'] ?? null;
                    $freeMem = $data['free_memory'] ?? ($data['freeMem'] ?? ($data['free_heap'] ?? null));
                    $device = IoTDevice::firstOrNew(['terminal_name' => $terminal]);
                    $device->device_type = 'camera';
                    $device->status = in_array($status, ['online', 'offline', 'error']) ? $status : 'online';
                    $device->ip_address = is_string($ip) ? $ip : null;
                    $device->wifi_strength = is_numeric($wifi) ? (int)$wifi : null;
                    $device->uptime = is_numeric($uptime) ? (int)$uptime : null;
                    $device->free_memory = is_numeric($freeMem) ? (int)$freeMem : null;
                    $device->last_seen = now();
                    $device->save();
                }

                elseif ($suffix === 'camera/result') {
                    // Log result for visibility; photo upload is handled via HTTP in IoTController
                    $success = is_array($data) ? ($data['success'] ?? null) : null;
                    $msg = is_array($data) ? ($data['message'] ?? null) : null;

                    $log = new AccessLog();
                    $log->action = 'camera_result';
                    $log->user = null;
                    $log->key_name = null;
                    $log->device = 'camera';
                    $log->save();

                    if (is_bool($success)) {
                        Log::info('Camera result', ['success' => $success, 'message' => $msg]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('MQTT ingestion error: ' . $e->getMessage(), [
                    'topic' => $topic,
                    'payload' => $message,
                ]);
            }
        }, 0);

        $start = microtime(true);
        while (true) {
            $client->loop(true);

            if ($timeout > 0) {
                $elapsed = microtime(true) - $start;
                if ($elapsed >= $timeout) {
                    $this->info('Timeout reached; disconnecting MQTT.');
                    break;
                }
            }
        }

        try {
            $client->disconnect();
        } catch (\Throwable $e) {
            // Ignore disconnect errors
        }

        return self::SUCCESS;
    }
}
