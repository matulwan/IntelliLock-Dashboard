#if !defined(ARDUINO_ARCH_ESP32)
#error "Not compiling for ESP32. Select an ESP32 board in Tools → Board."
#endif

// ==================== LIBRARIES ====================
#include <Arduino.h>
#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Adafruit_Fingerprint.h>
#include <HardwareSerial.h>
#include <ArduinoJson.h>

// ==================== PIN DEFINITIONS ====================
#define SS_PIN 15           // RFID SDA
#define RST_PIN 13          // RFID RST
#define BOX_LOCK_PIN 14     // Solenoid for box lock
#define DEVICE_NAME "lab_key_box"

// Key slot RFID readers (if you have individual readers per key)
#define KEY1_SS_PIN 16
#define KEY2_SS_PIN 17
#define KEY3_SS_PIN 18
#define KEY4_SS_PIN 19
#define KEY5_SS_PIN 21

// ==================== WiFi + Laravel API ====================
const char *LARAVEL_API_BASE = "http://192.168.1.100:8000/api/iot";
const char *ssid = "Rije";
const char *password = "1sampai6";

// ==================== OBJECTS ====================
LiquidCrystal_I2C lcd(0x27, 16, 2);
MFRC522 mfrc522(SS_PIN, RST_PIN);
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

uint64_t clearDisplayTimer = 0;
bool needDisplayUpdate = true;
bool boxUnlocked = false;
unsigned long boxUnlockTime = 0;
const unsigned long BOX_UNLOCK_DURATION = 10000; // 10 seconds

// ==================== FUNCTIONS ====================
void authenticateBoxAccess(String uid = "", int fingerprintId = -1, String type = "rfid");
void handleKeyTransaction(String keyRfidUid, String action);
void unlockBox();
void lockBox();
void scanForKeys();
void sendHeartbeat();
void clearDisplayIn(int mSec = 5000);

// ==================== HELPER FUNCTIONS ====================
void unlockBox() {
  digitalWrite(BOX_LOCK_PIN, LOW); // Unlock solenoid
  boxUnlocked = true;
  boxUnlockTime = millis();
  
  lcd.clear();
  lcd.print("Box Unlocked");
  lcd.setCursor(0, 1);
  lcd.print("Take/Return Keys");
  
  Serial.println("Key box unlocked for 10 seconds");
}

void lockBox() {
  digitalWrite(BOX_LOCK_PIN, HIGH); // Lock solenoid
  boxUnlocked = false;
  
  lcd.clear();
  lcd.print("Box Locked");
  lcd.setCursor(0, 1);
  lcd.print("Scan to Access");
  
  Serial.println("Key box locked");
}

void clearDisplayIn(int mSec) {
  clearDisplayTimer = millis() + mSec;
  needDisplayUpdate = true;
}

void authenticateBoxAccess(String uid, int fingerprintId, String type) {
  HTTPClient http;
  String url = String(LARAVEL_API_BASE) + "/authenticate";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  StaticJsonDocument<300> doc;
  doc["terminal"] = DEVICE_NAME;
  doc["type"] = type;
  
  if (type == "rfid" && uid.length() > 0) {
    doc["uid"] = uid;
  } else if (type == "fingerprint" && fingerprintId >= 0) {
    doc["fingerprint_id"] = fingerprintId;
  }
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  Serial.println("Box access request: " + jsonString);
  
  int httpResponseCode = http.POST(jsonString);
  
  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.println("Response: " + response);
    
    StaticJsonDocument<500> responseDoc;
    deserializeJson(responseDoc, response);
    
    String status = responseDoc["status"];
    String name = responseDoc["name"];
    String message = responseDoc["message"];
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Hi ");
    lcd.print(name);
    lcd.setCursor(0, 1);
    lcd.print(message);
    
    if (status == "success") {
      delay(2000);
      unlockBox();
    }
  } else {
    lcd.clear();
    lcd.print("Server Error");
    Serial.println("HTTP Error: " + String(httpResponseCode));
  }
  
  http.end();
  clearDisplayIn();
}

void handleKeyTransaction(String keyRfidUid, String action) {
  HTTPClient http;
  String url = String(LARAVEL_API_BASE) + "/key-transaction";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  StaticJsonDocument<400> doc;
  doc["key_rfid_uid"] = keyRfidUid;
  doc["action"] = action; // "checkout" or "checkin"
  doc["device"] = DEVICE_NAME;
  doc["timestamp"] = millis() / 1000;
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  Serial.println("Key transaction: " + jsonString);
  
  int httpResponseCode = http.POST(jsonString);
  
  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.println("Key transaction logged: " + response);
    
    lcd.clear();
    lcd.print("Key " + action);
    lcd.setCursor(0, 1);
    lcd.print("Logged!");
  }
  
  http.end();
}

void scanForKeys() {
  // This function would scan individual key slots
  // Implementation depends on your hardware setup
  // For now, just scan the main RFID reader for key tags
  
  if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    String uid = "";
    for (int i = 0; i < mfrc522.uid.size; i++) {
      uid += String(mfrc522.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();
    
    Serial.println("Key RFID detected: " + uid);
    
    // Determine if this is a key being taken or returned
    // You might need additional logic here based on your setup
    handleKeyTransaction(uid, "checkout"); // or "checkin"
    
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
  }
}

void sendHeartbeat() {
  HTTPClient http;
  String url = String(LARAVEL_API_BASE) + "/heartbeat";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  StaticJsonDocument<200> doc;
  doc["terminal"] = DEVICE_NAME;
  doc["timestamp"] = millis() / 1000;
  doc["box_status"] = boxUnlocked ? "unlocked" : "locked";
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  http.POST(jsonString);
  http.end();
}

// ==================== SETUP ====================
void setup() {
  pinMode(BOX_LOCK_PIN, OUTPUT);
  lockBox(); // Start with box locked
  
  lcd.init();
  lcd.backlight();
  Serial.begin(115200);
  SPI.begin();
  mfrc522.PCD_Init();
  
  // WiFi connection
  lcd.print("Connecting WiFi...");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(300);
    Serial.print(".");
  }
  
  lcd.clear();
  lcd.print("WiFi Connected");
  Serial.println("IP: " + WiFi.localIP().toString());
  
  // Fingerprint setup
  mySerial.begin(57600, SERIAL_8N1, 25, 26);
  finger.begin(57600);
  
  if (finger.verifyPassword()) {
    Serial.println("Fingerprint sensor ready");
  }
  
  delay(2000);
  lockBox(); // Show initial state
}

// ==================== MAIN LOOP ====================
void loop() {
  // Handle display updates
  if (needDisplayUpdate && millis() > clearDisplayTimer) {
    if (!boxUnlocked) {
      lcd.clear();
      lcd.print("Lab Key Box");
      lcd.setCursor(0, 1);
      lcd.print("Scan to Access");
    }
    needDisplayUpdate = false;
  }
  
  // Auto-lock box after timeout
  if (boxUnlocked && (millis() - boxUnlockTime > BOX_UNLOCK_DURATION)) {
    lockBox();
  }
  
  // Box access authentication
  if (!boxUnlocked) {
    // RFID scan for box access
    if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
      String uid = "";
      for (int i = 0; i < mfrc522.uid.size; i++) {
        uid += String(mfrc522.uid.uidByte[i], HEX);
      }
      uid.toUpperCase();
      
      authenticateBoxAccess(uid, -1, "rfid");
      
      mfrc522.PICC_HaltA();
      mfrc522.PCD_StopCrypto1();
    }
    
    // Fingerprint scan for box access
    uint8_t p = finger.getImage();
    if (p == FINGERPRINT_OK) {
      p = finger.image2Tz(1);
      if (p == FINGERPRINT_OK && finger.fingerFastSearch() == FINGERPRINT_OK) {
        int id = finger.fingerID;
        authenticateBoxAccess("", id, "fingerprint");
      }
    }
  } else {
    // Box is unlocked - scan for key transactions
    scanForKeys();
  }
  
  // Send periodic heartbeat
  static unsigned long lastHeartbeat = 0;
  if (millis() - lastHeartbeat > 30000) { // Every 30 seconds
    sendHeartbeat();
    lastHeartbeat = millis();
  }
  
  delay(100);
}
