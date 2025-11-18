// ============================================================================
// INTELLI-LOCK ESP32-CAM CONTROLLER - MQTT VERSION WITH BUFFER FIX
// ============================================================================
// Hardware: ESP32-CAM (AI-Thinker)
// Purpose: Capture photos 4 seconds after door unlock trigger via MQTT
// Communication: MQTT Protocol
// Fix: Clears camera buffer to ensure fresh photos (not cached/old frames)
// ============================================================================

#include <WiFi.h>
#include <WebServer.h>
#include <PubSubClient.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "esp_camera.h"
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// ==================== WIFI CREDENTIALS ====================
const char *ssid = "TECNO POVA 7 Ultra 5G";
const char *password = "johan123";

// ==================== MQTT CONFIGURATION ====================
const char* mqtt_server = "10.169.195.71";
const int mqtt_port = 1883;
const char* mqtt_user = "";
const char* mqtt_pass = "";

// MQTT Topics
const char* TOPIC_CAMERA_TRIGGER = "intellilock/camera/trigger";
const char* TOPIC_CAMERA_STATUS = "intellilock/camera/status";
const char* TOPIC_CAMERA_RESULT = "intellilock/camera/result";

// ==================== LARAVEL API ENDPOINT ====================
const char* uploadURL = "http://10.169.195.71:8000/api/intellilock/upload";

// ==================== TIMING CONFIGURATION ====================
const unsigned long CAPTURE_DELAY = 6000;  // 4 seconds delay after door unlock

// ==================== CAMERA PINS (AI-Thinker ESP32-CAM) ====================
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

#define FLASH_LED_PIN      4

// ==================== WEB SERVER & MQTT CLIENT ====================
WebServer server(80);
WiFiClient espClient;
PubSubClient mqttClient(espClient);

// ==================== DELAYED CAPTURE VARIABLES ====================
bool captureScheduled = false;
unsigned long captureTime = 0;
String scheduledUserInfo = "";

// ==================== FUNCTION DECLARATIONS ====================
void setupCamera();
void setupWiFi();
void setupMQTT();
void reconnectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int length);
void handleCapture();
void handleRoot();
bool captureAndUpload(String userInfo = "");
void publishStatus(String status);
void publishResult(bool success, String message);
void scheduleCapture(String userInfo);
void checkScheduledCapture();

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(100);
  
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);
  
  Serial.println("\n╔════════════════════════════════════════╗");
  Serial.println("║    ESP32-CAM INTELLI-LOCK CAMERA      ║");
  Serial.println("║  MQTT + 4s Delay + Buffer Clear Fix   ║");
  Serial.println("╚════════════════════════════════════════╝\n");
  
  pinMode(FLASH_LED_PIN, OUTPUT);
  digitalWrite(FLASH_LED_PIN, LOW);
  
  setupCamera();
  setupWiFi();
  setupMQTT();
  
  server.on("/", handleRoot);
  server.on("/capture", handleCapture);
  server.begin();
  
  Serial.println("✅ HTTP Server started");
  Serial.print("📍 Camera URL: http://");
  Serial.print(WiFi.localIP());
  Serial.println("/capture");
  Serial.println("\n✅ SYSTEM READY - Listening for MQTT triggers...");
  Serial.println("⏱️  Capture delay: 4 seconds after door unlock\n");
  
  publishStatus("online");
}

// ==================== CAMERA SETUP ====================
void setupCamera() {
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  
  if(psramFound()){
    config.frame_size = FRAMESIZE_UXGA;
    config.jpeg_quality = 10;
    config.fb_count = 2;
  } else {
    config.frame_size = FRAMESIZE_SVGA;
    config.jpeg_quality = 12;
    config.fb_count = 1;
  }
  
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("❌ Camera init failed with error 0x%x\n", err);
    return;
  }
  
  Serial.println("✅ Camera initialized");
  
  sensor_t * s = esp_camera_sensor_get();
  s->set_brightness(s, 0);
  s->set_contrast(s, 0);
  s->set_saturation(s, 0);
  s->set_special_effect(s, 0);
  s->set_whitebal(s, 1);
  s->set_awb_gain(s, 1);
  s->set_wb_mode(s, 0);
  s->set_exposure_ctrl(s, 1);
  s->set_aec2(s, 0);
  s->set_ae_level(s, 0);
  s->set_aec_value(s, 300);
  s->set_gain_ctrl(s, 1);
  s->set_agc_gain(s, 0);
  s->set_gainceiling(s, (gainceiling_t)0);
  s->set_bpc(s, 0);
  s->set_wpc(s, 1);
  s->set_raw_gma(s, 1);
  s->set_lenc(s, 1);
  s->set_hmirror(s, 0);
  s->set_vflip(s, 0);
  s->set_dcw(s, 1);
  s->set_colorbar(s, 0);
  
  // Clear initial buffer
  Serial.println("🔄 Clearing initial camera buffer...");
  delay(500);
  for(int i = 0; i < 3; i++) {
    camera_fb_t * fb = esp_camera_fb_get();
    if(fb) {
      esp_camera_fb_return(fb);
      Serial.printf("   Cleared startup frame %d\n", i + 1);
    }
    delay(100);
  }
  Serial.println("✅ Camera ready for fresh captures");
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
  } else {
    Serial.println("❌ WiFi Connection Failed");
  }
}

// ==================== MQTT SETUP ====================
void setupMQTT() {
  mqttClient.setServer(mqtt_server, mqtt_port);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512);
  reconnectMQTT();
}

// ==================== MQTT RECONNECT ====================
void reconnectMQTT() {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }
  
  int attempts = 0;
  while (!mqttClient.connected() && attempts < 5) {
    Serial.print("🔌 Connecting to MQTT broker...");
    
    String clientId = "IntelliLock-Camera-";
    clientId += String(random(0xffff), HEX);
    
    if (mqttClient.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println(" ✅ Connected");
      mqttClient.subscribe(TOPIC_CAMERA_TRIGGER);
      Serial.println("📥 Subscribed to camera trigger topic");
      publishStatus("online");
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
  
  if (String(topic) == TOPIC_CAMERA_TRIGGER) {
    Serial.println("\n🚪 Door unlock detected via MQTT!");
    
    StaticJsonDocument<200> doc;
    DeserializationError error = deserializeJson(doc, message);
    
    String userInfo = "";
    if (!error) {
      userInfo = doc["user"] | "";
    }
    
    digitalWrite(FLASH_LED_PIN, HIGH);
    delay(50);
    digitalWrite(FLASH_LED_PIN, LOW);
    
    scheduleCapture(userInfo);
  }
}

// ==================== SCHEDULE CAPTURE ====================
void scheduleCapture(String userInfo) {
  captureScheduled = true;
  captureTime = millis() + CAPTURE_DELAY;
  scheduledUserInfo = userInfo;
  
  Serial.println("⏱️  Photo capture scheduled in 4 seconds...");
  Serial.println("💡 User has time to open door and position for photo");
  Serial.print("⏰ Will capture at: ");
  Serial.println(captureTime);
}

// ==================== CHECK SCHEDULED CAPTURE ====================
void checkScheduledCapture() {
  if (captureScheduled && millis() >= captureTime) {
    Serial.println("\n⏰ 4 SECONDS ELAPSED - CAPTURING NOW!");
    Serial.println("📸 Say cheese! 📸");
    
    for(int i = 0; i < 3; i++) {
      digitalWrite(FLASH_LED_PIN, HIGH);
      delay(100);
      digitalWrite(FLASH_LED_PIN, LOW);
      delay(100);
    }
    
    delay(200);
    
    bool success = captureAndUpload(scheduledUserInfo);
    
    if (success) {
      publishResult(true, "Photo captured and uploaded successfully (4s delay)");
      Serial.println("✅ Photo taken after 4-second delay");
    } else {
      publishResult(false, "Failed to capture or upload photo");
      Serial.println("❌ Photo capture failed");
    }
    
    captureScheduled = false;
    scheduledUserInfo = "";
  }
  
  // Countdown display
  static unsigned long lastCountdown = 0;
  if (captureScheduled && millis() - lastCountdown > 1000) {
    unsigned long remaining = (captureTime - millis()) / 1000;
    if (remaining > 0 && remaining <= 4) {
      Serial.printf("⏱️  Countdown: %lu seconds remaining...\n", remaining);
    }
    lastCountdown = millis();
  }
}

// ==================== MAIN LOOP ====================
void loop() {
  server.handleClient();
  checkScheduledCapture();
  
  static unsigned long lastWiFiCheck = 0;
  if (millis() - lastWiFiCheck > 30000) {
    lastWiFiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️  WiFi disconnected, reconnecting...");
      WiFi.reconnect();
    }
  }
  
  if (!mqttClient.connected()) {
    reconnectMQTT();
  }
  mqttClient.loop();
  
  static unsigned long lastStatusPublish = 0;
  if (millis() - lastStatusPublish > 60000) {
    lastStatusPublish = millis();
    publishStatus("online");
  }
}

// ==================== CAPTURE AND UPLOAD - WITH BUFFER CLEARING ====================
bool captureAndUpload(String userInfo) {
  Serial.println("\n📸 ═══════════════════════════════════════");
  Serial.println("📸 STARTING PHOTO CAPTURE PROCESS");
  Serial.println("📸 ═══════════════════════════════════════");
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi not connected, cannot upload");
    return false;
  }
  
  Serial.print("✅ WiFi connected, RSSI: ");
  Serial.println(WiFi.RSSI());
  
  // BUFFER CLEARING - THE FIX!
  Serial.println("\n🔄 STEP 1: Clearing old frames from buffer...");
  int framesCleared = 0;
  for(int i = 0; i < 4; i++) {
    camera_fb_t * fb_discard = esp_camera_fb_get();
    if(fb_discard) {
      esp_camera_fb_return(fb_discard);
      framesCleared++;
      Serial.printf("   ✓ Discarded old frame %d/%d\n", i + 1, 4);
      delay(100);
    } else {
      Serial.printf("   ⚠ No frame to discard at position %d\n", i + 1);
      delay(50);
    }
  }
  
  Serial.printf("✅ Buffer cleared (%d old frames removed)\n", framesCleared);
  delay(200);
  
  // CAPTURE FRESH PHOTO
  Serial.println("\n📸 STEP 2: Capturing FRESH photo...");
  
  digitalWrite(FLASH_LED_PIN, HIGH);
  delay(150);
  
  camera_fb_t * fb = esp_camera_fb_get();
  
  digitalWrite(FLASH_LED_PIN, LOW);
  
  if (!fb) {
    Serial.println("❌ Camera capture failed!");
    return false;
  }
  
  Serial.printf("✅ Photo captured successfully!\n");
  Serial.printf("   Size: %d bytes (%.1f KB)\n", fb->len, fb->len / 1024.0);
  Serial.printf("   Width: %d px, Height: %d px\n", fb->width, fb->height);
  
  // UPLOAD TO SERVER
  Serial.println("\n📤 STEP 3: Uploading to server...");
  
  WiFiClient client;
  HTTPClient http;
  
  Serial.print("🌐 Target URL: ");
  Serial.println(uploadURL);
  
  http.setTimeout(20000);
  http.setConnectTimeout(10000);
  
  if (!http.begin(client, uploadURL)) {
    Serial.println("❌ Failed to begin HTTP connection");
    esp_camera_fb_return(fb);
    return false;
  }
  
  Serial.println("✅ HTTP connection established");
  
  http.addHeader("Content-Type", "image/jpeg");
  http.addHeader("Content-Length", String(fb->len));
  http.addHeader("Connection", "close");
  
  if (userInfo != "") {
    http.addHeader("X-User-ID", userInfo);
    Serial.println("📝 User ID: " + userInfo);
  }
  http.addHeader("X-Timestamp", String(millis()));
  http.addHeader("X-Device", "ESP32-CAM");
  
  Serial.println("📤 Uploading photo data...");
  unsigned long uploadStart = millis();
  
  int httpCode = http.POST(fb->buf, fb->len);
  
  unsigned long uploadDuration = millis() - uploadStart;
  Serial.printf("⏱️  Upload took: %lu ms\n", uploadDuration);
  Serial.printf("📡 HTTP Response Code: %d\n", httpCode);
  
  bool success = false;
  
  if (httpCode > 0) {
    if (httpCode == HTTP_CODE_OK || httpCode == 200) {
      Serial.println("\n✅ ═══════════════════════════════════════");
      Serial.println("✅ PHOTO UPLOADED SUCCESSFULLY!");
      Serial.println("✅ ═══════════════════════════════════════");
      String response = http.getString();
      Serial.print("📡 Server response: ");
      Serial.println(response);
      success = true;
    } else {
      Serial.printf("⚠️  Unexpected HTTP code: %d\n", httpCode);
      String response = http.getString();
      Serial.print("Server response: ");
      Serial.println(response);
    }
  } else {
    Serial.println("\n❌ ═══════════════════════════════════════");
    Serial.println("❌ UPLOAD FAILED!");
    Serial.println("❌ ═══════════════════════════════════════");
    Serial.printf("Error code: %d\n", httpCode);
  }
  
  http.end();
  esp_camera_fb_return(fb);
  
  Serial.println("═══════════════════════════════════════\n");
  
  return success;
}

// ==================== WEB SERVER HANDLERS ====================
void handleRoot() {
  String html = "<!DOCTYPE html><html><head><title>ESP32-CAM IntelliLock</title>";
  html += "<meta name='viewport' content='width=device-width, initial-scale=1'>";
  html += "<style>body{font-family:Arial;text-align:center;padding:50px;background:#f0f0f0;}";
  html += "h1{color:#333;}.status{color:green;font-weight:bold;font-size:20px;}";
  html += "button{padding:15px 30px;font-size:18px;background:#4CAF50;color:white;border:none;border-radius:5px;cursor:pointer;margin:10px;}";
  html += "button:hover{background:#45a049;}.info{margin:20px;padding:15px;background:white;border-radius:5px;}</style></head>";
  html += "<body><h1>📷 ESP32-CAM IntelliLock</h1><div class='info'>";
  html += "<p>Status: <span class='status'>Active ✅</span></p>";
  html += "<p>MQTT: " + String(mqttClient.connected() ? "Connected ✅" : "Disconnected ❌") + "</p>";
  html += "<p>⏱️ Capture Delay: 4 seconds</p>";
  html += "<p>IP: " + WiFi.localIP().toString() + "</p></div>";
  html += "<p><a href='/capture'><button>📸 Test Capture (Immediate)</button></a></p>";
  html += "</body></html>";
  server.send(200, "text/html", html);
}

void handleCapture() {
  Serial.println("\n📸 HTTP Capture request received (immediate)...");
  
  if (captureAndUpload("HTTP_MANUAL")) {
    server.send(200, "application/json", "{\"status\":\"success\"}");
  } else {
    server.send(500, "application/json", "{\"status\":\"error\"}");
  }
}

// ==================== PUBLISH FUNCTIONS ====================
void publishStatus(String status) {
  if (!mqttClient.connected()) return;

  StaticJsonDocument<300> doc;
  doc["device"] = "camera";
  doc["status"] = status;
  doc["wifi_rssi"] = WiFi.RSSI();
  doc["uptime"] = millis();
  doc["free_heap"] = ESP.getFreeHeap();
  doc["ip_address"] = WiFi.localIP().toString();

  char jsonBuffer[300];
  serializeJson(doc, jsonBuffer);

  if (mqttClient.publish(TOPIC_CAMERA_STATUS, jsonBuffer)) {
    Serial.println("📊 Status published to MQTT");
  }
}

void publishResult(bool success, String message) {
  if (!mqttClient.connected()) return;

  StaticJsonDocument<200> doc;
  doc["success"] = success;
  doc["message"] = message;
  doc["timestamp"] = millis();

  char jsonBuffer[200];
  serializeJson(doc, jsonBuffer);

  mqttClient.publish(TOPIC_CAMERA_RESULT, jsonBuffer);
  Serial.println("📡 Result published to MQTT");
}