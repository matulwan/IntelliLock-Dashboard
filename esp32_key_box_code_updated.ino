#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Adafruit_Fingerprint.h>

#define SS_PIN 15
#define RST_PIN 13
#define RELAY_PIN 14
#define BUZZER_PIN 27
#define CAM_TRIGGER 32

// Fingerprint UART
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger(&mySerial);

// WiFi credentials
const char* ssid = "Rije";
const char* password = "1sampai6";

// Laravel endpoint
const char* apiURL = "http://192.168.56.1:8000/api/intellilock/event";

MFRC522 mfrc522(SS_PIN, RST_PIN);
bool doorOpen = false;
unsigned long doorTimer = 0;
const unsigned long timeoutMs = 20000; // 20s before buzzer alert

// ==================== FUNCTION DECLARATIONS ====================
// Add these forward declarations so functions can be called before they're defined
void sendEvent(String action, String extra = "");
bool checkRFID();
bool checkFingerprint();
void unlockDoor();
void lockDoor();
void triggerCamera();
void buzzerAlert();

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  SPI.begin();
  mfrc522.PCD_Init();
  mySerial.begin(57600, SERIAL_8N1, 25, 26);
  pinMode(RELAY_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(CAM_TRIGGER, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH); // Locked by default
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(CAM_TRIGGER, LOW);

  WiFi.begin(ssid, password);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    Serial.print(".");
    delay(500);
  }
  Serial.println("\n✅ WiFi Connected");

  if (finger.verifyPassword())
    Serial.println("✅ Fingerprint ready");
  else
    Serial.println("❌ Fingerprint not found");
}

// ==================== MAIN LOOP ====================
void loop() {
  if (checkFingerprint() || checkRFID()) {
    unlockDoor();
    triggerCamera();
    sendEvent("door_unlocked");
    doorOpen = true;
    doorTimer = millis();
  }

  if (doorOpen && (millis() - doorTimer > timeoutMs)) {
    Serial.println("⚠️ Timeout: door left open or tag not scanned");
    buzzerAlert();
  }
}

// ==================== RFID CHECK ====================
bool checkRFID() {
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) return false;

  Serial.print("RFID UID: ");
  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  Serial.println(uid);

  // First tap = take key | Second tap = return key
  if (doorOpen) {
    lockDoor();
    sendEvent("key_tagged_returned", uid);
    doorOpen = false;
  } else {
    sendEvent("key_tagged_taken", uid);
  }

  mfrc522.PICC_HaltA();
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
    Serial.print("Fingerprint match: ID=");
    Serial.println(finger.fingerID);
    sendEvent("fingerprint_match");
    return true;
  }
  return false;
}

// ==================== DOOR CONTROL ====================
void unlockDoor() {
  Serial.println("🔓 Door Unlocked");
  digitalWrite(RELAY_PIN, LOW);
  delay(5000);
}

void lockDoor() {
  Serial.println("🔒 Door Locked");
  digitalWrite(RELAY_PIN, HIGH);
  digitalWrite(BUZZER_PIN, LOW);
}

// ==================== CAMERA TRIGGER ====================
void triggerCamera() {
  Serial.println("📷 Triggering ESP32-CAM");
  digitalWrite(CAM_TRIGGER, HIGH);
  delay(100);
  digitalWrite(CAM_TRIGGER, LOW);
}

// ==================== BUZZER ALERT ====================
void buzzerAlert() {
  for (int i = 0; i < 5; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(200);
    digitalWrite(BUZZER_PIN, LOW);
    delay(200);
  }
  sendEvent("door_timeout_alert");
}

// ==================== SEND EVENT TO LARAVEL ====================
void sendEvent(String action, String extra) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️ WiFi not connected, event not sent");
    return;
  }

  HTTPClient http;
  http.begin(apiURL);
  http.addHeader("Content-Type", "application/json");

  String payload = "{\"action\":\"" + action + "\",\"extra\":\"" + extra + "\"}";
  int code = http.POST(payload);

  Serial.printf("📡 Sent: %s | Code: %d\n", payload.c_str(), code);

  if (code == 200) {
    Serial.println("✅ Event sent successfully");
  } else {
    Serial.printf("❌ Failed to send event (HTTP %d)\n", code);
  }

  http.end();
}