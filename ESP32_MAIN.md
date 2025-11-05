// ============================================================================
// INTELLI-LOCK MAIN ESP32 CONTROLLER - MQTT VERSION
// ============================================================================
// Hardware: ESP32 DevKit, RFID RC522, Fingerprint Sensor, Relay, Buzzer
// Purpose: Main controller for door lock, RFID/fingerprint authentication
// Communication: MQTT Protocol
// ============================================================================

#include <WiFi.h>
#include <PubSubClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Adafruit_Fingerprint.h>
#include <ArduinoJson.h>

// ==================== PIN DEFINITIONS ====================
#define SS_PIN 15
#define RST_PIN 13
#define RELAY_PIN 14
#define BUZZER_PIN 27
#define DOOR_SENSOR 35  // Optional: to detect if door is actually closed

// ==================== HARDWARE INITIALIZATION ====================
HardwareSerial mySerial(2);  // Fingerprint UART (RX=25, TX=26)
Adafruit_Fingerprint finger(&mySerial);
MFRC522 mfrc522(SS_PIN, RST_PIN);

// ==================== WIFI CREDENTIALS ====================
const char *ssid = "TECNO POVA 7 Ultra 5G";
const char *password = "johan123";

// ==================== MQTT CONFIGURATION ====================
const char* mqtt_server = "10.35.181.71";  // Your MQTT broker IP
const int mqtt_port = 1883;
const char* mqtt_user = "";  // Leave empty if no authentication
const char* mqtt_pass = "";  // Leave empty if no authentication

// MQTT Topics
const char* TOPIC_EVENT = "intellilock/events";
const char* TOPIC_CAMERA_TRIGGER = "intellilock/camera/trigger";
const char* TOPIC_STATUS = "intellilock/status";
const char* TOPIC_COMMAND = "intellilock/command";  // For remote commands

// ==================== MQTT CLIENT ====================
WiFiClient espClient;
PubSubClient mqttClient(espClient);

// ==================== SYSTEM STATE VARIABLES ====================
bool doorUnlocked = false;
unsigned long doorOpenTime = 0;
const unsigned long DOOR_TIMEOUT = 20000;  // 20 seconds timeout
const unsigned long DOOR_UNLOCK_DURATION = 5000;  // Keep unlocked for 5s

String currentUserUID = "";  // Store UID of user who unlocked
bool waitingForKeyTag = false;  // Flag to indicate waiting for key tag scan

// ==================== KEY TAG DATABASE (5 KEYS) ====================
// ⚠️ UPDATE THESE UIDs AFTER SCANNING YOUR KEY TAGS!
struct KeyTag {
  String uid;
  String keyName;
  bool isTaken;
};

KeyTag keyTags[5] = {
  {"AABBCCDD", "Key 1", false},  // Replace with actual key tag UID
  {"11223344", "Key 2", false},  // Replace with actual key tag UID
  {"55667788", "Key 3", false},  // Replace with actual key tag UID
  {"99AABBCC", "Key 4", false},  // Replace with actual key tag UID
  {"DDEEFF00", "Key 5", false}   // Replace with actual key tag UID
};

// ==================== FUNCTION DECLARATIONS ====================
void setupWiFi();
void setupHardware();
void setupMQTT();
void reconnectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int length);
void checkWiFiConnection();
bool authenticateUser();
bool checkRFID(String &uid);
bool checkFingerprint();
void unlockDoor();
void lockDoor();
void triggerCameraMQTT();
void buzzerAlert(int times = 3);
void buzzerBeep(int times = 1);
void publishEvent(String action, String extra = "", String keyInfo = "");
void publishStatus();
int findKeyByUID(String uid);
void updateKeyStatus(int keyIndex, bool taken);

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(100);
  
  Serial.println("\n╔════════════════════════════════════════╗");
  Serial.println("║   INTELLI-LOCK SMART KEY MANAGEMENT   ║");
  Serial.println("║         MQTT Protocol Version         ║");
  Serial.println("╚════════════════════════════════════════╝\n");
  
  setupHardware();
  setupWiFi();
  setupMQTT();
  
  Serial.println("\n✅ SYSTEM READY - Waiting for user authentication...\n");
  publishStatus();
}

// ==================== HARDWARE SETUP ====================
void setupHardware() {
  // Initialize GPIO pins
  pinMode(RELAY_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  
  digitalWrite(RELAY_PIN, HIGH);  // Locked state (HIGH = locked)
  digitalWrite(BUZZER_PIN, LOW);
  
  // Initialize SPI and RFID
  SPI.begin();
  mfrc522.PCD_Init();
  Serial.println("✅ RFID reader initialized");
  
  // Initialize fingerprint sensor
  mySerial.begin(57600, SERIAL_8N1, 25, 26);
  delay(100);
  
  if (finger.verifyPassword()) {
    Serial.println("✅ Fingerprint sensor connected");
  } else {
    Serial.println("⚠️  Fingerprint sensor not found - check wiring");
  }
  
  // Startup beep
  buzzerBeep(2);
}

// ==================== WIFI SETUP ====================
void setupWiFi() {
  Serial.print("📡 Connecting to WiFi: ");
  Serial.println(ssid);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("✅ WiFi Connected");
    Serial.print("📍 IP Address: ");
    Serial.println(WiFi.localIP());
    buzzerBeep(1);
  } else {
    Serial.println("❌ WiFi Connection Failed");
    Serial.println("⚠️  System will operate in offline mode");
  }
}

// ==================== MQTT SETUP ====================
void setupMQTT() {
  mqttClient.setServer(mqtt_server, mqtt_port);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512);  // Increase buffer for larger messages
  
  reconnectMQTT();
}

// ==================== MQTT RECONNECT ====================
void reconnectMQTT() {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }
  
  // Loop until we're reconnected
  int attempts = 0;
  while (!mqttClient.connected() && attempts < 5) {
    Serial.print("🔌 Connecting to MQTT broker...");
    
    // Create a unique client ID
    String clientId = "IntelliLock-Main-";
    clientId += String(random(0xffff), HEX);
    
    // Attempt to connect
    if (mqttClient.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println(" ✅ Connected");
      
      // Subscribe to command topic
      mqttClient.subscribe(TOPIC_COMMAND);
      Serial.println("📥 Subscribed to command topic");
      
      // Publish online status
      publishStatus();
      buzzerBeep(1);
      
    } else {
      Serial.print(" ❌ Failed, rc=");
      Serial.print(mqttClient.state());
      Serial.println(" - Retrying in 5 seconds");
      delay(5000);
      attempts++;
    }
  }
}

// ==================== MQTT CALLBACK ====================
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  Serial.print("📩 MQTT Message received [");
  Serial.print(topic);
  Serial.print("]: ");
  
  String message = "";
  for (int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  Serial.println(message);
  
  // Parse JSON command
  StaticJsonDocument<200> doc;
  DeserializationError error = deserializeJson(doc, message);
  
  if (error) {
    Serial.println("❌ Failed to parse MQTT command");
    return;
  }
  
  // Handle remote commands
  String cmd = doc["command"] | "";
  
  if (cmd == "unlock") {
    Serial.println("🔓 Remote unlock command received");
    currentUserUID = "REMOTE";
    unlockDoor();
    triggerCameraMQTT();
    waitingForKeyTag = true;
    doorOpenTime = millis();
  } 
  else if (cmd == "lock") {
    Serial.println("🔒 Remote lock command received");
    lockDoor();
    waitingForKeyTag = false;
  }
  else if (cmd == "status") {
    publishStatus();
  }
}

// ==================== MAIN LOOP ====================
void loop() {
  checkWiFiConnection();
  
  // Maintain MQTT connection
  if (!mqttClient.connected()) {
    reconnectMQTT();
  }
  mqttClient.loop();
  
  // STATE 1: Waiting for user authentication (RFID or Fingerprint)
  if (!doorUnlocked && !waitingForKeyTag) {
    if (authenticateUser()) {
      unlockDoor();
      triggerCameraMQTT();  // Trigger camera via MQTT
      waitingForKeyTag = true;
      doorOpenTime = millis();
    }
  }
  
  // STATE 2: Door unlocked, waiting for key tag scan or return
  if (waitingForKeyTag) {
    String tagUID = "";
    if (checkRFID(tagUID)) {
      int keyIndex = findKeyByUID(tagUID);
      
      if (keyIndex >= 0) {
        // Key found in database
        if (!keyTags[keyIndex].isTaken) {
          // Taking the key
          Serial.printf("🔑 %s TAKEN by %s\n", keyTags[keyIndex].keyName.c_str(), currentUserUID.c_str());
          updateKeyStatus(keyIndex, true);
          publishEvent("key_taken", currentUserUID, keyTags[keyIndex].keyName);
          buzzerBeep(2);
        } else {
          // Returning the key
          Serial.printf("🔑 %s RETURNED by %s\n", keyTags[keyIndex].keyName.c_str(), currentUserUID.c_str());
          updateKeyStatus(keyIndex, false);
          publishEvent("key_returned", currentUserUID, keyTags[keyIndex].keyName);
          buzzerBeep(3);
        }
        
        // Reset state
        waitingForKeyTag = false;
        lockDoor();
        currentUserUID = "";
      } else {
        // Unknown key tag
        Serial.println("❌ Unknown key tag detected");
        publishEvent("unknown_key_tag", tagUID);
        buzzerAlert(2);
      }
    }
    
    // Check for timeout
    if (millis() - doorOpenTime > DOOR_TIMEOUT) {
      Serial.println("⏰ TIMEOUT: Key tag not scanned!");
      buzzerAlert(5);
      publishEvent("door_timeout", currentUserUID);
      
      // Reset state
      waitingForKeyTag = false;
      lockDoor();
      currentUserUID = "";
    }
  }
  
  // Publish status every 30 seconds
  static unsigned long lastStatusPublish = 0;
  if (millis() - lastStatusPublish > 30000) {
    lastStatusPublish = millis();
    publishStatus();
  }
  
  delay(100);  // Small delay to prevent rapid polling
}

// ==================== USER AUTHENTICATION ====================
bool authenticateUser() {
  // Check fingerprint first
  if (checkFingerprint()) {
    Serial.println("✅ Authentication: FINGERPRINT");
    currentUserUID = "FP_" + String(finger.fingerID);
    publishEvent("user_authenticated", currentUserUID, "fingerprint");
    return true;
  }
  
  // Then check RFID card
  String cardUID = "";
  if (checkRFID(cardUID)) {
    // Only process if it's NOT a key tag (check against key database)
    if (findKeyByUID(cardUID) == -1) {
      Serial.println("✅ Authentication: RFID CARD");
      currentUserUID = cardUID;
      publishEvent("user_authenticated", cardUID, "rfid");
      return true;
    }
  }
  
  return false;
}

// ==================== RFID CHECK ====================
bool checkRFID(String &uid) {
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    return false;
  }

  uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  
  Serial.print("📱 RFID Detected: ");
  Serial.println(uid);
  
  mfrc522.PICC_HaltA();
  delay(500);  // Prevent multiple reads
  return true;
}

// ==================== FINGERPRINT CHECK ====================
bool checkFingerprint() {
  uint8_t p = finger.getImage();
  if (p != FINGERPRINT_OK) return false;

  p = finger.image2Tz();
  if (p != FINGERPRINT_OK) return false;

  p = finger.fingerFastSearch();
  if (p == FINGERPRINT_OK) {
    Serial.printf("👆 Fingerprint Match - ID: %d (Confidence: %d)\n", finger.fingerID, finger.confidence);
    return true;
  }
  
  if (p == FINGERPRINT_NOTFOUND) {
    Serial.println("❌ Fingerprint not recognized");
  }
  return false;
}

// ==================== DOOR CONTROL ====================
void unlockDoor() {
  Serial.println("\n🔓 DOOR UNLOCKED");
  digitalWrite(RELAY_PIN, LOW);  // Unlock solenoid
  doorUnlocked = true;
  buzzerBeep(2);
  publishEvent("door_unlocked", currentUserUID);
}

void lockDoor() {
  Serial.println("🔒 DOOR LOCKED\n");
  digitalWrite(RELAY_PIN, HIGH);  // Lock solenoid
  doorUnlocked = false;
  digitalWrite(BUZZER_PIN, LOW);  // Ensure buzzer is off
  buzzerBeep(1);
  publishEvent("door_locked", currentUserUID);
}

// ==================== CAMERA TRIGGER VIA MQTT ====================
void triggerCameraMQTT() {
  if (!mqttClient.connected()) {
    Serial.println("⚠️  Camera trigger failed - MQTT offline");
    return;
  }

  Serial.println("📷 Triggering ESP32-CAM via MQTT...");
  
  StaticJsonDocument<200> doc;
  doc["user"] = currentUserUID;
  doc["timestamp"] = millis();
  
  char jsonBuffer[200];
  serializeJson(doc, jsonBuffer);
  
  if (mqttClient.publish(TOPIC_CAMERA_TRIGGER, jsonBuffer)) {
    Serial.println("✅ Camera trigger sent via MQTT");
  } else {
    Serial.println("❌ Failed to send camera trigger");
  }
}

// ==================== BUZZER FUNCTIONS ====================
void buzzerBeep(int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(100);
    digitalWrite(BUZZER_PIN, LOW);
    delay(100);
  }
}

void buzzerAlert(int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(300);
    digitalWrite(BUZZER_PIN, LOW);
    delay(300);
  }
}

// ==================== KEY MANAGEMENT ====================
int findKeyByUID(String uid) {
  for (int i = 0; i < 5; i++) {
    if (keyTags[i].uid == uid && keyTags[i].uid != "") {
      return i;
    }
  }
  return -1;  // Not found
}

void updateKeyStatus(int keyIndex, bool taken) {
  if (keyIndex >= 0 && keyIndex < 5) {
    keyTags[keyIndex].isTaken = taken;
  }
}

// ==================== WIFI CONNECTION CHECK ====================
void checkWiFiConnection() {
  static unsigned long lastCheck = 0;
  if (millis() - lastCheck > 30000) {  // Check every 30 seconds
    lastCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️  WiFi disconnected, reconnecting...");
      WiFi.reconnect();
    }
  }
}

// ==================== PUBLISH EVENT TO MQTT ====================
void publishEvent(String action, String extra, String keyInfo) {
  if (!mqttClient.connected()) {
    Serial.println("⚠️  Event not sent - MQTT offline");
    return;
  }

  // Build JSON payload
  StaticJsonDocument<300> doc;
  doc["action"] = action;
  doc["user"] = extra;
  doc["key_info"] = keyInfo;
  doc["timestamp"] = millis();
  doc["device"] = "main_controller";

  char jsonBuffer[300];
  serializeJson(doc, jsonBuffer);

  if (mqttClient.publish(TOPIC_EVENT, jsonBuffer)) {
    Serial.printf("📡 Event published: %s [%s]\n", action.c_str(), extra.c_str());
  } else {
    Serial.println("❌ Failed to publish event");
  }
}

// ==================== PUBLISH STATUS ====================
void publishStatus() {
  if (!mqttClient.connected()) {
    return;
  }

  StaticJsonDocument<400> doc;
  doc["device"] = "main_controller";
  doc["status"] = "online";
  doc["door_locked"] = !doorUnlocked;
  doc["waiting_for_key"] = waitingForKeyTag;
  doc["wifi_rssi"] = WiFi.RSSI();
  doc["uptime"] = millis();
  
  // Add key status
  JsonArray keys = doc.createNestedArray("keys");
  for (int i = 0; i < 5; i++) {
    JsonObject key = keys.createNestedObject();
    key["name"] = keyTags[i].keyName;
    key["taken"] = keyTags[i].isTaken;
  }

  char jsonBuffer[400];
  serializeJson(doc, jsonBuffer);

  mqttClient.publish(TOPIC_STATUS, jsonBuffer);
  Serial.println("📊 Status published to MQTT");
}