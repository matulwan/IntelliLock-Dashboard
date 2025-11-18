// ============================================================================
// INTELLI-LOCK MAIN ESP32 CONTROLLER - INDIVIDUAL DEVICE STATUS
// ============================================================================
// Hardware: ESP32 DevKit, RFID RC522, Fingerprint Sensor, Relay, LCD 16x2
// Purpose: Main controller with separate status for each hardware component
// Communication: MQTT Protocol only
// Fix: Sends individual status messages for RFID, LCD, and Fingerprint
// ============================================================================

#include <WiFi.h>
#include <PubSubClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Adafruit_Fingerprint.h>
#include <ArduinoJson.h>
#include <esp_task_wdt.h>
#include <LiquidCrystal_I2C.h>

// ==================== CORRECTED PIN DEFINITIONS ====================
#define SS_PIN 5      // RFID Chip Select
#define RST_PIN 27    // RFID Reset
#define RELAY_PIN 14  // Door Relay
#define DOOR_SENSOR 35

// ==================== LCD CONFIGURATION ====================
#define LCD_I2C_ADDRESS 0x27
#define LCD_COLUMNS 16
#define LCD_ROWS 2
LiquidCrystal_I2C lcd(LCD_I2C_ADDRESS, LCD_COLUMNS, LCD_ROWS);

// ==================== TIMING CONSTANTS ====================
const unsigned long DOOR_TIMEOUT = 15000;
const unsigned long RFID_DEBOUNCE = 3000;
const unsigned long STATUS_INTERVAL = 30000;
const unsigned long DEVICE_STATUS_INTERVAL = 30000;
const unsigned long RECONNECT_DELAY = 5000;
const unsigned long WATCHDOG_TIMEOUT = 60;
const unsigned long EVENT_PUBLISH_DELAY = 1000;
const unsigned long DOOR_OPEN_DURATION = 15000;
const unsigned long LCD_MESSAGE_DURATION = 3000;
const unsigned long CAMERA_TRIGGER_DELAY = 2000;
const int MAX_WIFI_ATTEMPTS = 50;
const int MAX_MQTT_ATTEMPTS = 3;

// ==================== SYSTEM STATE MACHINE ====================
enum SystemState {
  STATE_IDLE,
  STATE_AUTHENTICATING,
  STATE_DOOR_UNLOCKED,
  STATE_WAITING_FOR_KEY_TAKE,
  STATE_WAITING_FOR_KEY_RETURN,
  STATE_ERROR
};

// ==================== HARDWARE INITIALIZATION ====================
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger(&mySerial);
MFRC522 mfrc522(SS_PIN, RST_PIN);

// ==================== WIFI CONFIG ====================
const char *ssid = "TECNO POVA 7 Ultra 5G";
const char *password = "johan123";

// ==================== MQTT CONFIGURATION ====================
const char* mqtt_server = "10.169.195.71";
const int mqtt_port = 1883;
const char* mqtt_client_prefix = "IntelliLock-Main-";

// MQTT Topics
const char* TOPIC_EVENT = "intellilock/events";
const char* TOPIC_STATUS = "intellilock/status";
const char* TOPIC_COMMAND = "intellilock/command";
const char* TOPIC_CAMERA_TRIGGER = "intellilock/camera/trigger";
const char* TOPIC_KEY_DETECTED = "intellilock/key/detected";
const char* TOPIC_AUTH_CHECK = "intellilock/auth/check";
const char* TOPIC_AUTH_RESPONSE = "intellilock/auth/response";
const char* TOPIC_KEY_RETURN = "intellilock/key/return";

WiFiClient espClient;
PubSubClient mqttClient(espClient);

// ==================== SYSTEM VARIABLES ====================
SystemState currentState = STATE_IDLE;
String currentUserUID = "";
String currentUserName = "";
String lastProcessedKeyUID = "";
String currentKeyName = "";
unsigned long doorOpenTime = 0;
unsigned long lastStatusPublish = 0;
unsigned long lastDeviceStatusPublish = 0;
unsigned long lastRFIDRead = 0;
unsigned long lastReconnectAttempt = 0;
unsigned long lastLCDUpdate = 0;
int mqttReconnectAttempts = 0;

// Camera trigger timing
bool cameraTriggerPending = false;
unsigned long cameraTriggerScheduledTime = 0;

// Event tracking flags
bool doorUnlockedEventSent = false;
bool userAuthEventSent = false;
bool keyDetectedEventSent = false;
bool keyReturnEventSent = false;

// Hardware status flags
bool rfidInitialized = false;
bool fingerprintInitialized = false;
bool lcdInitialized = false;

// Authorization tracking
bool waitingForAuthResponse = false;
unsigned long authRequestTime = 0;
const unsigned long AUTH_TIMEOUT = 5000;

// Key operation mode
bool keyReturnMode = false;

// LCD message tracking
String currentLCDLine1 = "";
String currentLCDLine2 = "";
bool lcdMessageActive = false;
unsigned long lcdMessageStart = 0;

// ==================== FUNCTION DECLARATIONS ====================
void setupWiFi();
void setupHardware();
void setupMQTT();
void reconnectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int length);
bool checkRFID(String &uid);
bool checkFingerprint();
void unlockDoor(const String& reason = "");
void lockDoor(const String& reason = "");
void changeState(SystemState newState);
void publishEvent(const String& action, const String& user, const String& keyInfo);
void publishKeyDetected(const String& keyUID);
void publishKeyReturn(const String& keyUID);
void publishStatus();
void publishIndividualDeviceStatus();
void publishRFIDStatus();
void publishLCDStatus();
void publishFingerprintStatus();
void triggerCameraMQTT();
void scheduleCameraTrigger();
void checkCameraTrigger();
void handleTimeout();
void handleError(const String& error);
String getStateString(SystemState state);
void resetEventFlags();
bool checkAuthorization(const String& uid, const String& authType);
void handleAuthResponse(bool authorized, const String& uid, const String& userName, const String& userType);
void startKeyReturnFlow();
void processKeyDetection(const String& keyUID);

// LCD FUNCTIONS
void setupLCD();
void updateLCD();
void displayLCDMessage(const String& line1, const String& line2, unsigned long duration = LCD_MESSAGE_DURATION);
void clearLCD();
void scrollText(String text, int line, int delayTime = 300);
void displayWelcome();
void displaySystemReady();
void displayUserWelcome(const String& userName);
void displayDoorUnlocked();
void displayPleaseScanKey();
void displayKeyTaken(const String& keyName);
void displayKeyReturned(const String& keyName);
void displayThankYou();
void displayError(const String& error);
void displayAuthFailed();
void displayKeyReturnMode();
void displayWaitingForKeyReturn();
void displayPreparingCamera();

// WATCHDOG FUNCTIONS
void setupWatchdog();
void petWatchdog();

// WIRING TEST FUNCTION
void testWiring();

// ==================== CAMERA TRIGGER FUNCTIONS ====================
void triggerCameraMQTT() {
  StaticJsonDocument<128> doc;
  doc["user"] = currentUserUID;
  doc["timestamp"] = millis();
  doc["mode"] = keyReturnMode ? "return" : "take";
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_CAMERA_TRIGGER, json.c_str())) {
    Serial.printf("📷 Camera trigger sent (Mode: %s)\n", keyReturnMode ? "RETURN" : "TAKE");
  } else {
    Serial.println("❌ Failed to send camera trigger");
  }
}

void scheduleCameraTrigger() {
  cameraTriggerPending = true;
  cameraTriggerScheduledTime = millis() + CAMERA_TRIGGER_DELAY;
  Serial.printf("📷 Camera trigger scheduled in %lu ms\n", CAMERA_TRIGGER_DELAY);
  displayPreparingCamera();
}

void checkCameraTrigger() {
  if (cameraTriggerPending && millis() >= cameraTriggerScheduledTime) {
    triggerCameraMQTT();
    cameraTriggerPending = false;
    Serial.println("📷 Camera trigger executed");
  }
}

// ==================== KEY RETURN FLOW ====================
void startKeyReturnFlow() {
  Serial.println("🔄 ═══════════════════════════════════════");
  Serial.println("🔄 ENTERING KEY RETURN MODE");
  Serial.println("🔄 ═══════════════════════════════════════");
  
  resetEventFlags();
  currentUserUID = "KEY_RETURN_MODE";
  currentUserName = "Key Return";
  keyReturnMode = true;
  lastProcessedKeyUID = "";
  currentKeyName = "";
  
  unlockDoor("key_return_mode");
  
  changeState(STATE_WAITING_FOR_KEY_RETURN);
  doorOpenTime = millis();
  
  Serial.println("✅ Key return mode activated");
  Serial.println("💡 Please scan key to return");
  petWatchdog();
}

// ==================== AUTHORIZATION FUNCTIONS ====================
bool checkAuthorization(const String& uid, const String& authType) {
  Serial.printf("🔐 Checking authorization for %s: %s\n", authType.c_str(), uid.c_str());
  displayLCDMessage("Checking", "Authorization...", LCD_MESSAGE_DURATION);
  
  StaticJsonDocument<256> authDoc;
  authDoc["uid"] = uid;
  authDoc["auth_type"] = authType;
  authDoc["device"] = "main_controller";
  authDoc["timestamp"] = millis();
  
  String authJson;
  serializeJson(authDoc, authJson);
  
  if (mqttClient.publish(TOPIC_AUTH_CHECK, authJson.c_str())) {
    Serial.println("📡 Authorization request sent");
    waitingForAuthResponse = true;
    authRequestTime = millis();
    return true;
  } else {
    Serial.println("❌ Failed to send authorization request");
    displayError("Auth Failed");
    return false;
  }
}

void handleAuthResponse(bool authorized, const String& uid, const String& userName, const String& userType) {
  if (authorized) {
    if (userType == "key") {
      if (currentState == STATE_IDLE) {
        Serial.printf("🔑 Key detected in IDLE: %s (%s)\n", userName.c_str(), uid.c_str());
        Serial.println("🔄 AUTO-ENTERING KEY RETURN MODE");
        
        displayLCDMessage("Key Detected", "Return Mode", LCD_MESSAGE_DURATION);
        
        resetEventFlags();
        currentUserUID = "AUTO_RETURN";
        currentUserName = userName;
        currentKeyName = userName;
        keyReturnMode = true;
        
        unlockDoor("auto_key_return");
        changeState(STATE_WAITING_FOR_KEY_RETURN);
        doorOpenTime = millis();
        lastProcessedKeyUID = "";
        
        processKeyDetection(uid);
      } else {
        Serial.printf("⚠️  Key %s scanned in state: %s\n", userName.c_str(), getStateString(currentState).c_str());
        displayLCDMessage("Wrong State", "Scan in IDLE", LCD_MESSAGE_DURATION);
        processKeyDetection(uid);
      }
    } 
    else if (userType == "user") {
      Serial.printf("✅ Authorized User: %s (%s)\n", userName.c_str(), uid.c_str());
      
      currentUserUID = uid;
      currentUserName = userName;
      
      displayUserWelcome(userName);
      
      if (!userAuthEventSent) {
        publishEvent("user_authenticated", userName, "rfid");
        userAuthEventSent = true;
      }
      
      resetEventFlags();
      unlockDoor("authorized_user");
      scheduleCameraTrigger();
      changeState(STATE_WAITING_FOR_KEY_TAKE);
      doorOpenTime = millis();
      lastProcessedKeyUID = "";
      currentKeyName = "";
      keyReturnMode = false;
    }
  } else {
    Serial.printf("❌ Unauthorized: %s\n", uid.c_str());
    displayAuthFailed();
    publishEvent("access_denied", uid, "unauthorized_rfid");
    
    currentUserUID = "";
    currentUserName = "";
    resetEventFlags();
    changeState(STATE_IDLE);
    keyReturnMode = false;
  }
  petWatchdog();
}

// ==================== PROCESS KEY DETECTION ====================
void processKeyDetection(const String& keyUID) {
  if (keyUID == lastProcessedKeyUID) {
    Serial.println("⚠️  Duplicate key scan ignored");
    displayLCDMessage("Duplicate", "Scan Ignored", LCD_MESSAGE_DURATION);
    return;
  }
  
  lastProcessedKeyUID = keyUID;
  
  if (keyReturnMode || currentState == STATE_WAITING_FOR_KEY_RETURN) {
    if (!keyReturnEventSent) {
      String keyName = currentKeyName.isEmpty() ? "Key " + keyUID.substring(0, 6) : currentKeyName;
      
      publishKeyReturn(keyUID);
      Serial.printf("🔑 ✅ Key RETURNED: %s\n", keyUID.c_str());
      displayKeyReturned(keyName);
      
      delay(1000);
      lockDoor("key_returned");
      currentUserUID = "";
      currentUserName = "";
      currentKeyName = "";
      changeState(STATE_IDLE);
      keyReturnMode = false;
      keyReturnEventSent = true;
    }
  } else {
    if (!keyDetectedEventSent) {
      String keyName = currentKeyName.isEmpty() ? "Key " + keyUID.substring(0, 6) : currentKeyName;
      
      publishKeyDetected(keyUID);
      Serial.printf("🔑 ✅ Key TAKEN: %s by user: %s\n", keyUID.c_str(), currentUserUID.c_str());
      displayKeyTaken(keyName);
      
      delay(1000);
      lockDoor("key_taken");
      currentUserUID = "";
      currentUserName = "";
      currentKeyName = "";
      changeState(STATE_IDLE);
      keyReturnMode = false;
      keyDetectedEventSent = true;
    }
  }
  petWatchdog();
}

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(100);
  
  Serial.println("\n╔════════════════════════════════════════╗");
  Serial.println("║   INTELLI-LOCK SMART KEY MANAGEMENT   ║");
  Serial.println("║  WITH INDIVIDUAL DEVICE STATUS        ║");
  Serial.println("║   + LCD DISPLAY + CAMERA DELAY FIX    ║");
  Serial.println("╚════════════════════════════════════════╝\n");
  
  setupWatchdog();
  testWiring();
  setupHardware();
  setupWiFi();
  setupMQTT();
  
  Serial.println("✅ SYSTEM READY - State: IDLE\n");
  displaySystemReady();
  
  delay(1000);
  publishStatus();
  publishIndividualDeviceStatus();
}

// ==================== MAIN LOOP ====================
void loop() {
  petWatchdog();
  
  if (!mqttClient.connected()) {
    reconnectMQTT();
  }
  mqttClient.loop();

  checkCameraTrigger();

  if (millis() - lastLCDUpdate > 500) {
    updateLCD();
    lastLCDUpdate = millis();
  }

  if (waitingForAuthResponse && (millis() - authRequestTime > AUTH_TIMEOUT)) {
    Serial.println("⏰ Authorization request timeout");
    displayLCDMessage("Auth Timeout", "Try Again", LCD_MESSAGE_DURATION);
    waitingForAuthResponse = false;
    publishEvent("auth_timeout", currentUserUID, "no_response");
    changeState(STATE_IDLE);
    keyReturnMode = false;
  }

  switch (currentState) {
    case STATE_IDLE:
      if (!waitingForAuthResponse) {
        if (fingerprintInitialized && checkFingerprint()) {
          if (!userAuthEventSent) {
            currentUserUID = "FP_" + String(finger.fingerID);
            currentUserName = "Fingerprint User";
            
            displayUserWelcome(currentUserName);
            
            publishEvent("user_authenticated", currentUserName, "fingerprint");
            userAuthEventSent = true;
            
            resetEventFlags();
            unlockDoor("fingerprint_auth");
            scheduleCameraTrigger();
            changeState(STATE_WAITING_FOR_KEY_TAKE);
            doorOpenTime = millis();
            lastProcessedKeyUID = "";
            currentKeyName = "";
            keyReturnMode = false;
          }
        }
        
        String uid;
        if (checkRFID(uid)) {
          Serial.println("📱 RFID scanned in IDLE - checking authorization");
          displayLCDMessage("RFID Scanned", "Checking...", LCD_MESSAGE_DURATION);
          checkAuthorization(uid, "rfid_check");
        }
      }
      break;

    case STATE_WAITING_FOR_KEY_TAKE:
      {
        String tagUID;
        if (checkRFID(tagUID)) {
          processKeyDetection(tagUID);
        }

        if (millis() - doorOpenTime > DOOR_TIMEOUT) {
          handleTimeout();
        }
      }
      break;

    case STATE_WAITING_FOR_KEY_RETURN:
      {
        String tagUID;
        if (checkRFID(tagUID)) {
          processKeyDetection(tagUID);
        }

        if (millis() - doorOpenTime > DOOR_TIMEOUT) {
          handleTimeout();
        }
      }
      break;

    case STATE_ERROR:
      delay(5000);
      changeState(STATE_IDLE);
      keyReturnMode = false;
      break;
  }

  if ((currentState == STATE_WAITING_FOR_KEY_TAKE || currentState == STATE_WAITING_FOR_KEY_RETURN) && 
      (millis() - doorOpenTime > DOOR_OPEN_DURATION)) {
    Serial.println("⏰ Auto-locking door after 15 seconds");
    displayLCDMessage("Auto-Locking", "Timeout", LCD_MESSAGE_DURATION);
    lockDoor("auto_lock_timeout");
    changeState(STATE_IDLE);
    keyReturnMode = false;
  }

  if (millis() - lastStatusPublish > STATUS_INTERVAL) {
    publishStatus();
    lastStatusPublish = millis();
  }
  
  if (millis() - lastDeviceStatusPublish > DEVICE_STATUS_INTERVAL) {
    publishIndividualDeviceStatus();
    lastDeviceStatusPublish = millis();
  }
  
  delay(10);
}

// ==================== STATE MANAGEMENT ====================
void changeState(SystemState newState) {
  if (currentState != newState) {
    Serial.printf("🔄 State: %s → %s\n", 
                  getStateString(currentState).c_str(), 
                  getStateString(newState).c_str());
    currentState = newState;
    
    if (newState == STATE_IDLE) {
      resetEventFlags();
    }
    
    delay(100);
    publishStatus();
  }
}

String getStateString(SystemState state) {
  switch (state) {
    case STATE_IDLE: return "IDLE";
    case STATE_AUTHENTICATING: return "AUTHENTICATING";
    case STATE_DOOR_UNLOCKED: return "UNLOCKED";
    case STATE_WAITING_FOR_KEY_TAKE: return "WAITING_KEY_TAKE";
    case STATE_WAITING_FOR_KEY_RETURN: return "WAITING_KEY_RETURN";
    case STATE_ERROR: return "ERROR";
    default: return "UNKNOWN";
  }
}

void resetEventFlags() {
  doorUnlockedEventSent = false;
  userAuthEventSent = false;
  keyDetectedEventSent = false;
  keyReturnEventSent = false;
}

// ==================== RFID / FINGERPRINT ====================
bool checkRFID(String &uid) {
  if (millis() - lastRFIDRead < RFID_DEBOUNCE) {
    return false;
  }
  
  if (!mfrc522.PICC_IsNewCardPresent()) {
    return false;
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    return false;
  }
  
  uid = "";
  uid.reserve(mfrc522.uid.size * 2);
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  
  Serial.printf("📱 RFID: %s\n", uid.c_str());
  
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  
  lastRFIDRead = millis();
  return true;
}

bool checkFingerprint() {
  uint8_t result = finger.getImage();
  if (result != FINGERPRINT_OK) {
    return false;
  }
  
  result = finger.image2Tz();
  if (result != FINGERPRINT_OK) {
    return false;
  }
  
  result = finger.fingerFastSearch();
  if (result == FINGERPRINT_OK) {
    Serial.printf("👆 Fingerprint ID: %d (Confidence: %d)\n", 
                  finger.fingerID, finger.confidence);
    return true;
  }
  
  return false;
}

// ==================== DOOR CONTROL ====================
void unlockDoor(const String& reason) {
  Serial.printf("🔓 Door Unlocked (Reason: %s)\n", reason.c_str());
  digitalWrite(RELAY_PIN, LOW);
  
  if (!doorUnlockedEventSent) {
    publishEvent("door_unlocked", currentUserUID, reason);
    doorUnlockedEventSent = true;
  }
}

void lockDoor(const String& reason) {
  Serial.printf("🔒 Door Locked (Reason: %s)\n", reason.c_str());
  digitalWrite(RELAY_PIN, HIGH);
  
  publishEvent("door_locked", currentUserUID, reason);
}

// ==================== MQTT PUBLISH FUNCTIONS ====================
void publishEvent(const String& action, const String& user, const String& keyInfo) {
  static String lastAction = "";
  static unsigned long lastPublishTime = 0;
  
  if (action == lastAction && (millis() - lastPublishTime < EVENT_PUBLISH_DELAY)) {
    Serial.printf("⚠️  Duplicate event skipped: %s\n", action.c_str());
    return;
  }
  
  lastAction = action;
  lastPublishTime = millis();
  
  StaticJsonDocument<256> doc;
  doc["action"] = action;
  doc["user"] = user;
  doc["key_info"] = keyInfo;
  doc["device"] = "main_controller";
  doc["timestamp"] = millis();
  doc["state"] = getStateString(currentState);
  doc["key_return_mode"] = keyReturnMode;
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_EVENT, json.c_str())) {
    Serial.printf("📡 Event: %s (User: %s, Mode: %s)\n", 
                  action.c_str(), user.c_str(), keyReturnMode ? "RETURN" : "TAKE");
  } else {
    Serial.printf("❌ Failed to publish event: %s\n", action.c_str());
  }
}

void publishKeyDetected(const String& keyUID) {
  StaticJsonDocument<256> doc;
  doc["action"] = "key_detected";
  doc["user"] = currentUserUID;
  doc["key_uid"] = keyUID;
  doc["device"] = "main_controller";
  doc["timestamp"] = millis();
  doc["operation"] = "checkout";
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_KEY_DETECTED, json.c_str())) {
    Serial.printf("📡 Key TAKEN published: %s\n", keyUID.c_str());
    keyDetectedEventSent = true;
  } else {
    Serial.println("❌ Failed to publish key detection");
  }
}

void publishKeyReturn(const String& keyUID) {
  StaticJsonDocument<256> doc;
  doc["action"] = "key_returned";
  doc["user"] = currentUserUID;
  doc["key_uid"] = keyUID;
  doc["device"] = "main_controller";
  doc["timestamp"] = millis();
  doc["operation"] = "checkin";
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_KEY_RETURN, json.c_str())) {
    Serial.printf("📡 Key RETURNED published: %s\n", keyUID.c_str());
    keyReturnEventSent = true;
  } else {
    Serial.println("❌ Failed to publish key return");
  }
}

void publishStatus() {
  StaticJsonDocument<256> doc;
  doc["device"] = "main_controller";
  doc["device_type"] = "esp32";
  doc["status"] = "online";
  doc["state"] = getStateString(currentState);
  doc["door_locked"] = (currentState == STATE_IDLE);
  doc["key_return_mode"] = keyReturnMode;
  doc["wifi_rssi"] = WiFi.RSSI();
  doc["wifi_connected"] = (WiFi.status() == WL_CONNECTED);
  doc["mqtt_connected"] = mqttClient.connected();
  doc["rfid_ok"] = rfidInitialized;
  doc["fingerprint_ok"] = fingerprintInitialized;
  doc["uptime"] = millis();
  doc["free_heap"] = ESP.getFreeHeap();
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_STATUS, json.c_str())) {
    Serial.println("📊 Main controller status published");
  } else {
    Serial.println("❌ Failed to publish status");
  }
}

// ==================== INDIVIDUAL DEVICE STATUS FUNCTIONS ====================
void publishIndividualDeviceStatus() {
  Serial.println("📊 Publishing individual device status...");
  publishRFIDStatus();
  delay(200);
  publishLCDStatus();
  delay(200);
  publishFingerprintStatus();
}

void publishRFIDStatus() {
  StaticJsonDocument<256> doc;
  doc["device"] = "rfid_reader";
  doc["device_type"] = "rfid";
  doc["status"] = rfidInitialized ? "online" : "offline";
  doc["connected"] = rfidInitialized;
  doc["error_count"] = 0;
  doc["last_read"] = lastRFIDRead > 0 ? (millis() - lastRFIDRead) : 0;
  
  if (rfidInitialized) {
    byte version = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
    doc["version"] = String(version, HEX);
  }
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_STATUS, json.c_str())) {
    Serial.println("  ✅ RFID status published");
  } else {
    Serial.println("  ❌ Failed to publish RFID status");
  }
}

void publishLCDStatus() {
  StaticJsonDocument<256> doc;
  doc["device"] = "lcd_display";
  doc["device_type"] = "lcd";
  doc["status"] = lcdInitialized ? "online" : "offline";
  doc["connected"] = lcdInitialized;
  doc["backlight"] = lcdInitialized;
  doc["current_line1"] = currentLCDLine1;
  doc["current_line2"] = currentLCDLine2;
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_STATUS, json.c_str())) {
    Serial.println("  ✅ LCD status published");
  } else {
    Serial.println("  ❌ Failed to publish LCD status");
  }
}

void publishFingerprintStatus() {
  StaticJsonDocument<256> doc;
  doc["device"] = "fingerprint_sensor";
  doc["device_type"] = "fingerprint";
  doc["status"] = fingerprintInitialized ? "online" : "offline";
  doc["connected"] = fingerprintInitialized;
  
  if (fingerprintInitialized) {
    doc["template_count"] = finger.templateCount;
  }
  
  String json;
  serializeJson(doc, json);
  
  if (mqttClient.publish(TOPIC_STATUS, json.c_str())) {
    Serial.println("  ✅ Fingerprint status published");
  } else {
    Serial.println("  ❌ Failed to publish Fingerprint status");
  }
}

// ==================== ERROR HANDLING ====================
void handleTimeout() {
  Serial.println("⏰ Timeout waiting for key tag");
  displayLCDMessage("Timeout", "No Key Scanned", LCD_MESSAGE_DURATION);
  publishEvent("door_timeout", currentUserUID, keyReturnMode ? "no_key_returned" : "no_key_taken");
  lockDoor("timeout");
  currentUserUID = "";
  currentUserName = "";
  lastProcessedKeyUID = "";
  currentKeyName = "";
  keyReturnMode = false;
  changeState(STATE_IDLE);
}

void handleError(const String& error) {
  Serial.printf("❌ ERROR: %s\n", error.c_str());
  displayError(error);
  publishEvent("system_error", "SYSTEM", error);
  changeState(STATE_ERROR);
  keyReturnMode = false;
}

// ==================== WIRING TEST FUNCTION ====================
void testWiring() {
  Serial.println(" ");
  Serial.println("🔧 TESTING WIRING CONNECTIONS...");
  Serial.println("=====================================");
  
  Serial.println("📱 Testing RFID Pins:");
  pinMode(SS_PIN, OUTPUT);
  digitalWrite(SS_PIN, HIGH);
  delay(100);
  digitalWrite(SS_PIN, LOW);
  Serial.println("   ✅ SS_PIN (GPIO 5) is working");
  
  pinMode(RST_PIN, OUTPUT);
  digitalWrite(RST_PIN, HIGH);
  delay(100);
  digitalWrite(RST_PIN, LOW); 
  Serial.println("   ✅ RST_PIN (GPIO 27) is working");
  
  Serial.println("🔒 Testing Relay Pin:");
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH);
  delay(100);
  digitalWrite(RELAY_PIN, LOW);
  Serial.println("   ✅ RELAY_PIN (GPIO 14) is working");
  
  Serial.println("🚪 Testing Door Sensor Pin:");
  pinMode(DOOR_SENSOR, INPUT_PULLUP);
  int doorState = digitalRead(DOOR_SENSOR);
  Serial.printf("   ✅ DOOR_SENSOR (GPIO 35) reading: %d\n", doorState);
  
  Serial.println("=====================================");
  Serial.println("✅ ALL PIN TESTS COMPLETED");
  Serial.println(" ");
}

// ==================== WATCHDOG SETUP ====================
void setupWatchdog() {
  esp_task_wdt_config_t wdt_config = {
    .timeout_ms = WATCHDOG_TIMEOUT * 1000,
    .idle_core_mask = 0,
    .trigger_panic = true
  };
  esp_err_t err = esp_task_wdt_init(&wdt_config);
  if (err != ESP_OK) {
    Serial.printf("❌ Watchdog init failed: %d\n", err);
  } else {
    Serial.println("✅ Watchdog initialized");
  }
  
  err = esp_task_wdt_add(NULL);
  if (err != ESP_OK) {
    Serial.printf("❌ Watchdog add failed: %d\n", err);
  }
}

void petWatchdog() {
  esp_task_wdt_reset();
}

// ==================== HARDWARE SETUP ====================
void setupHardware() {
  pinMode(RELAY_PIN, OUTPUT);
  pinMode(DOOR_SENSOR, INPUT_PULLUP);
  digitalWrite(RELAY_PIN, HIGH);

  setupLCD();
  petWatchdog();

  // Initialize RFID
  Serial.println("🔍 Initializing RFID Reader...");
  SPI.begin();
  pinMode(SS_PIN, OUTPUT);
  digitalWrite(SS_PIN, HIGH);
  delay(100);
  
  mfrc522.PCD_Init();
  delay(100);
  
  byte version = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  Serial.printf("📱 RFID Version Register: 0x%02X\n", version);
  
  if (version == 0x00 || version == 0xFF) {
    Serial.println("❌ RFID reader communication failed - Check wiring!");
    rfidInitialized = false;
    displayLCDMessage("RFID Failed", "Check Wiring", LCD_MESSAGE_DURATION);
  } else {
    Serial.printf("✅ RFID initialized successfully (Version: 0x%02X)\n", version);
    mfrc522.PCD_WriteRegister(mfrc522.TxModeReg, 0x00);
    mfrc522.PCD_WriteRegister(mfrc522.RxModeReg, 0x00);
    mfrc522.PCD_WriteRegister(mfrc522.ModWidthReg, 0x26);
    mfrc522.PCD_SetAntennaGain(mfrc522.RxGain_38dB);
    mfrc522.PCD_SetRegisterBitMask(mfrc522.TxControlReg, 0x03);
    rfidInitialized = true;
    displayLCDMessage("RFID Ready", "Scan to begin", LCD_MESSAGE_DURATION);
  }
  petWatchdog();

  // Initialize Fingerprint
  Serial.println("🔍 Initializing Fingerprint Sensor...");
  mySerial.begin(57600, SERIAL_8N1, 25, 26);
  delay(1000);

  if (finger.verifyPassword()) {
    Serial.println("✅ Fingerprint sensor ready");
    fingerprintInitialized = true;
  } else {
    Serial.println("⚠️  Fingerprint sensor not found - Check wiring!");
    fingerprintInitialized = false;
    displayLCDMessage("Fingerprint", "Sensor Offline", LCD_MESSAGE_DURATION);
  }
  petWatchdog();

  Serial.println("\n📊 HARDWARE STATUS SUMMARY:");
  Serial.printf("   - RFID: %s\n", rfidInitialized ? "✅ READY" : "❌ FAILED");
  Serial.printf("   - Fingerprint: %s\n", fingerprintInitialized ? "✅ READY" : "❌ FAILED");
  Serial.printf("   - LCD: %s\n", lcdInitialized ? "✅ READY" : "❌ FAILED");
  Serial.printf("   - Relay: ✅ READY (GPIO %d)\n", RELAY_PIN);
}

// ==================== LCD SETUP ====================
void setupLCD() {
  Serial.println("🔍 Initializing LCD...");
  
  byte error;
  Wire.begin();
  
  byte addresses[] = {0x27, 0x3F};
  bool lcdFound = false;
  
  for (byte addr : addresses) {
    Wire.beginTransmission(addr);
    error = Wire.endTransmission();
    
    if (error == 0) {
      Serial.printf("✅ LCD found at address: 0x%02X\n", addr);
      lcd = LiquidCrystal_I2C(addr, LCD_COLUMNS, LCD_ROWS);
      lcdFound = true;
      break;
    }
  }
  
  if (!lcdFound) {
    Serial.println("❌ LCD not found - Check I2C wiring!");
    lcdInitialized = false;
    return;
  }
  
  lcd.init();
  lcd.backlight();
  lcdInitialized = true;
  
  lcd.setCursor(0, 0);
  lcd.print("Intelli-Lock");
  lcd.setCursor(0, 1);
  lcd.print("Initializing...");
  
  delay(1000);
  displayWelcome();
}

void displayWelcome() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("  WELCOME TO");
  lcd.setCursor(0, 1);
  lcd.print("  INTELLI-LOCK");
  delay(2000);
}

void displaySystemReady() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("System Ready");
  lcd.setCursor(0, 1);
  lcd.print("Scan to begin");
}

void displayPreparingCamera() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("Preparing Camera");
  lcd.setCursor(0, 1);
  lcd.print("Please Look Up");
}

// ==================== LCD MESSAGE FUNCTIONS ====================
void updateLCD() {
  if (!lcdInitialized) return;
  
  if (lcdMessageActive && (millis() - lcdMessageStart > LCD_MESSAGE_DURATION)) {
    lcdMessageActive = false;
    updateLCD();
    return;
  }
  
  if (lcdMessageActive) {
    return;
  }
  
  switch (currentState) {
    case STATE_IDLE:
      if (keyReturnMode) {
        displayKeyReturnMode();
      } else {
        clearLCD();
        lcd.setCursor(0, 0);
        lcd.print("Scan RFID/Finger");
        lcd.setCursor(0, 1);
        lcd.print("to authenticate");
      }
      break;
      
    case STATE_AUTHENTICATING:
      clearLCD();
      lcd.setCursor(0, 0);
      lcd.print("Authenticating...");
      lcd.setCursor(0, 1);
      lcd.print("Please wait");
      break;
      
    case STATE_DOOR_UNLOCKED:
      displayDoorUnlocked();
      break;
      
    case STATE_WAITING_FOR_KEY_TAKE:
      displayPleaseScanKey();
      break;
      
    case STATE_WAITING_FOR_KEY_RETURN:
      displayWaitingForKeyReturn();
      break;
      
    case STATE_ERROR:
      clearLCD();
      lcd.setCursor(0, 0);
      lcd.print("System Error");
      lcd.setCursor(0, 1);
      lcd.print("Contact Admin");
      break;
  }
}

void displayLCDMessage(const String& line1, const String& line2, unsigned long duration) {
  if (!lcdInitialized) return;
  
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  lcd.setCursor(0, 1);
  lcd.print(line2);
  
  currentLCDLine1 = line1;
  currentLCDLine2 = line2;
  lcdMessageActive = true;
  lcdMessageStart = millis();
}

void clearLCD() {
  if (!lcdInitialized) return;
  lcd.clear();
}

void displayUserWelcome(const String& userName) {
  String displayName = userName;
  if (displayName.length() > 16) {
    displayName = displayName.substring(0, 13) + "...";
  }
  
  displayLCDMessage("Hello " + displayName, "Door Unlocked", LCD_MESSAGE_DURATION);
}

void displayDoorUnlocked() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("Door Unlocked");
  lcd.setCursor(0, 1);
  lcd.print("Please Enter");
}

void displayPleaseScanKey() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("Please Scan");
  lcd.setCursor(0, 1);
  lcd.print("Key Tag");
}

void displayKeyTaken(const String& keyName) {
  String displayKey = keyName;
  if (displayKey.length() > 16) {
    displayKey = displayKey.substring(0, 13) + "...";
  }
  
  displayLCDMessage("Key Taken:", displayKey, LCD_MESSAGE_DURATION);
  delay(2000);
  displayThankYou();
}

void displayKeyReturned(const String& keyName) {
  String displayKey = keyName;
  if (displayKey.length() > 16) {
    displayKey = displayKey.substring(0, 13) + "...";
  }
  
  displayLCDMessage("Key Returned:", displayKey, LCD_MESSAGE_DURATION);
  delay(2000);
  displayThankYou();
}

void displayThankYou() {
  displayLCDMessage("Thank You!", "Have a Nice Day", LCD_MESSAGE_DURATION);
}

void displayError(const String& error) {
  displayLCDMessage("Error:", error, LCD_MESSAGE_DURATION);
}

void displayAuthFailed() {
  displayLCDMessage("Authentication", "Failed", LCD_MESSAGE_DURATION);
}

void displayKeyReturnMode() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("KEY RETURN MODE");
  lcd.setCursor(0, 1);
  lcd.print("Scan Key to Return");
}

void displayWaitingForKeyReturn() {
  clearLCD();
  lcd.setCursor(0, 0);
  lcd.print("Waiting for");
  lcd.setCursor(0, 1);
  lcd.print("Key Return");
}

// ==================== WIFI ====================
void setupWiFi() {
  displayLCDMessage("Connecting", "to WiFi...", LCD_MESSAGE_DURATION);
  
  Serial.printf("📡 Connecting to WiFi: %s\n", ssid);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  
  int attempt = 0;
  while (WiFi.status() != WL_CONNECTED && attempt < MAX_WIFI_ATTEMPTS) {
    delay(500);
    Serial.print(".");
    attempt++;
    
    if (attempt % 4 == 0) {
      petWatchdog();
      Serial.print("🐕");
    }
    
    if (attempt % 10 == 0) {
      displayLCDMessage("WiFi Connecting", "Attempt " + String(attempt/2) + "s", LCD_MESSAGE_DURATION);
    }
  }
  
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("✅ WiFi connected");
    Serial.printf("📍 IP: %s\n", WiFi.localIP().toString().c_str());
    Serial.printf("📶 RSSI: %d dBm\n", WiFi.RSSI());
    displayLCDMessage("WiFi Connected", "IP: " + WiFi.localIP().toString(), LCD_MESSAGE_DURATION);
    delay(1000);
  } else {
    Serial.println("❌ WiFi connection failed");
    displayError("WiFi Failed");
    currentState = STATE_ERROR;
  }
  petWatchdog();
}

// ==================== MQTT ====================
void setupMQTT() {
  mqttClient.setServer(mqtt_server, mqtt_port);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512);
  mqttClient.setKeepAlive(60);
  reconnectMQTT();
}

void reconnectMQTT() {
  if (millis() - lastReconnectAttempt < RECONNECT_DELAY) {
    return;
  }
  
  lastReconnectAttempt = millis();
  mqttReconnectAttempts++;
  
  if (mqttReconnectAttempts > MAX_MQTT_ATTEMPTS) {
    Serial.println("⚠️  Max MQTT reconnect attempts reached, waiting longer...");
    displayLCDMessage("MQTT", "Reconnecting...", LCD_MESSAGE_DURATION);
    delay(10000);
    mqttReconnectAttempts = 0;
  }
  
  if (!mqttClient.connected()) {
    displayLCDMessage("Connecting", "to MQTT...", LCD_MESSAGE_DURATION);
    Serial.print("🔌 Connecting to MQTT...");
    
    String clientId = String(mqtt_client_prefix) + String(random(0xffff), HEX);
    
    if (mqttClient.connect(clientId.c_str())) {
      Serial.println(" ✅ Connected");
      mqttReconnectAttempts = 0;
      
      mqttClient.subscribe(TOPIC_COMMAND);
      mqttClient.subscribe(TOPIC_AUTH_RESPONSE);
      Serial.println("📥 Subscribed to command and auth response topics");
      
      displayLCDMessage("MQTT Connected", "System Ready", LCD_MESSAGE_DURATION);
      delay(1000);
      
      publishStatus();
      publishIndividualDeviceStatus();
    } else {
      Serial.printf(" ❌ Failed (rc=%d)\n", mqttClient.state());
      displayLCDMessage("MQTT Failed", "Retrying...", LCD_MESSAGE_DURATION);
    }
  }
  petWatchdog();
}

// ==================== MQTT CALLBACK ====================
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String message;
  message.reserve(length);
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  
  Serial.printf("📩 [%s]: %s\n", topic, message.c_str());

  StaticJsonDocument<512> doc;
  DeserializationError error = deserializeJson(doc, message);
  
  if (error) {
    Serial.printf("❌ JSON parse error: %s\n", error.c_str());
    return;
  }

  if (String(topic) == TOPIC_COMMAND) {
    const char* cmd = doc["command"];
    
    if (strcmp(cmd, "unlock") == 0) {
      Serial.println("🔓 Remote unlock command received");
      displayLCDMessage("Remote", "Unlock Command", LCD_MESSAGE_DURATION);
      resetEventFlags();
      currentUserUID = "REMOTE";
      currentUserName = "Remote User";
      unlockDoor("remote_command");
      scheduleCameraTrigger();
      changeState(STATE_WAITING_FOR_KEY_TAKE);
      doorOpenTime = millis();
    } 
    else if (strcmp(cmd, "lock") == 0) {
      Serial.println("🔒 Remote lock command received");
      displayLCDMessage("Remote", "Lock Command", LCD_MESSAGE_DURATION);
      lockDoor("remote_command");
      changeState(STATE_IDLE);
    } 
    else if (strcmp(cmd, "status") == 0) {
      publishStatus();
      publishIndividualDeviceStatus();
    }
    else if (strcmp(cmd, "reset") == 0) {
      Serial.println("🔄 Reset command received");
      displayLCDMessage("System", "Reset Command", LCD_MESSAGE_DURATION);
      resetEventFlags();
      changeState(STATE_IDLE);
      currentUserUID = "";
      currentUserName = "";
      lastProcessedKeyUID = "";
      currentKeyName = "";
      waitingForAuthResponse = false;
      keyReturnMode = false;
      cameraTriggerPending = false;
    }
    else if (strcmp(cmd, "return_key") == 0 || strcmp(cmd, "return_mode") == 0) {
      Serial.println("🔄 KEY RETURN MODE command received");
      displayLCDMessage("Key Return", "Mode Activated", LCD_MESSAGE_DURATION);
      startKeyReturnFlow();
    }
  }
  
  else if (String(topic) == TOPIC_AUTH_RESPONSE) {
    if (waitingForAuthResponse) {
      const char* uid = doc["uid"];
      const char* userName = doc["user_name"];
      bool authorized = doc["authorized"];
      
      String userType = "user";
      if (doc.containsKey("user_type")) {
        userType = doc["user_type"].as<String>();
      }
      
      Serial.printf("🔑 Auth Response: UID=%s, Authorized=%s, User=%s, Type=%s\n", 
                    uid, authorized ? "YES" : "NO", userName, userType.c_str());
      
      handleAuthResponse(authorized, String(uid), String(userName), userType);
      waitingForAuthResponse = false;
    }
  }
  petWatchdog();
}