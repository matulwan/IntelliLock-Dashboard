// ============================================================================
// INTELLI-LOCK ESP32-CAM CONTROLLER - MQTT VERSION
// ============================================================================
// Hardware: ESP32-CAM (AI-Thinker)
// Purpose: Capture photos when triggered via MQTT and upload to Laravel server
// Communication: MQTT Protocol
// Note: Still provides HTTP web interface for manual testing
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
const char* mqtt_server = "10.35.181.71";  // Your MQTT broker IP
const int mqtt_port = 1883;
const char* mqtt_user = "";  // Leave empty if no authentication
const char* mqtt_pass = "";  // Leave empty if no authentication

// MQTT Topics
const char* TOPIC_CAMERA_TRIGGER = "intellilock/camera/trigger";
const char* TOPIC_CAMERA_STATUS = "intellilock/camera/status";
const char* TOPIC_CAMERA_RESULT = "intellilock/camera/result";

// ==================== LARAVEL API ENDPOINT ====================
const char* uploadURL = "http://10.35.181.71:8000/api/intellilock/upload";

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

#define FLASH_LED_PIN      4  // Built-in flash LED

// ==================== WEB SERVER & MQTT CLIENT ====================
WebServer server(80);
WiFiClient espClient;
PubSubClient mqttClient(espClient);

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

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(100);
  
  // Disable brownout detector
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);
  
  Serial.println("\n╔════════════════════════════════════════╗");
  Serial.println("║    ESP32-CAM INTELLI-LOCK CAMERA      ║");
  Serial.println("║         MQTT Protocol Version         ║");
  Serial.println("╚════════════════════════════════════════╝\n");
  
  // Initialize flash LED
  pinMode(FLASH_LED_PIN, OUTPUT);
  digitalWrite(FLASH_LED_PIN, LOW);
  
  setupCamera();
  setupWiFi();
  setupMQTT();
  
  // Setup web server routes (for manual testing)
  server.on("/", handleRoot);
  server.on("/capture", handleCapture);
  
  server.begin();
  Serial.println("✅ HTTP Server started");
  Serial.print("📍 Camera URL: http://");
  Serial.print(WiFi.localIP());
  Serial.println("/capture");
  Serial.println("\n✅ SYSTEM READY - Listening for MQTT triggers...\n");
  
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
  
  // High quality settings
  if(psramFound()){
    config.frame_size = FRAMESIZE_UXGA;  // 1600x1200
    config.jpeg_quality = 10;  // 0-63, lower = better quality
    config.fb_count = 2;
  } else {
    config.frame_size = FRAMESIZE_SVGA;  // 800x600
    config.jpeg_quality = 12;
    config.fb_count = 1;
  }
  
  // Initialize camera
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("❌ Camera init failed with error 0x%x\n", err);
    return;
  }
  
  Serial.println("✅ Camera initialized");
  
  // Adjust camera settings for better image quality
  sensor_t * s = esp_camera_sensor_get();
  s->set_brightness(s, 0);     // -2 to 2
  s->set_contrast(s, 0);       // -2 to 2
  s->set_saturation(s, 0);     // -2 to 2
  s->set_special_effect(s, 0); // 0 = No Effect
  s->set_whitebal(s, 1);       // 0 = disable , 1 = enable
  s->set_awb_gain(s, 1);       // 0 = disable , 1 = enable
  s->set_wb_mode(s, 0);        // 0 to 4 - if awb_gain enabled
  s->set_exposure_ctrl(s, 1);  // 0 = disable , 1 = enable
  s->set_aec2(s, 0);           // 0 = disable , 1 = enable
  s->set_ae_level(s, 0);       // -2 to 2
  s->set_aec_value(s, 300);    // 0 to 1200
  s->set_gain_ctrl(s, 1);      // 0 = disable , 1 = enable
  s->set_agc_gain(s, 0);       // 0 to 30
  s->set_gainceiling(s, (gainceiling_t)0);  // 0 to 6
  s->set_bpc(s, 0);            // 0 = disable , 1 = enable
  s->set_wpc(s, 1);            // 0 = disable , 1 = enable
  s->set_raw_gma(s, 1);        // 0 = disable , 1 = enable
  s->set_lenc(s, 1);           // 0 = disable , 1 = enable
  s->set_hmirror(s, 0);        // 0 = disable , 1 = enable
  s->set_vflip(s, 0);          // 0 = disable , 1 = enable
  s->set_dcw(s, 1);            // 0 = disable , 1 = enable
  s->set_colorbar(s, 0);       // 0 = disable , 1 = enable
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
  
  // Loop until we're reconnected
  int attempts = 0;
  while (!mqttClient.connected() && attempts < 5) {
    Serial.print("🔌 Connecting to MQTT broker...");
    
    // Create a unique client ID
    String clientId = "IntelliLock-Camera-";
    clientId += String(random(0xffff), HEX);
    
    // Attempt to connect
    if (mqttClient.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println(" ✅ Connected");
      
      // Subscribe to camera trigger topic
      mqttClient.subscribe(TOPIC_CAMERA_TRIGGER);
      Serial.println("📥 Subscribed to camera trigger topic");
      
      // Publish online status
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
  
  // Check if it's a camera trigger
  if (String(topic) == TOPIC_CAMERA_TRIGGER) {
    Serial.println("\n📸 Camera trigger received via MQTT!");
    
    // Parse JSON to get user info
    StaticJsonDocument<200> doc;
    DeserializationError error = deserializeJson(doc, message);
    
    String userInfo = "";
    if (!error) {
      userInfo = doc["user"] | "";
    }
    
    // Flash LED to indicate trigger
    digitalWrite(FLASH_LED_PIN, HIGH);
    delay(100);
    digitalWrite(FLASH_LED_PIN, LOW);
    delay(100);
    digitalWrite(FLASH_LED_PIN, HIGH);
    delay(100);
    digitalWrite(FLASH_LED_PIN, LOW);
    
    // Capture and upload
    bool success = captureAndUpload(userInfo);
    
    if (success) {
      publishResult(true, "Photo captured and uploaded successfully");
    } else {
      publishResult(false, "Failed to capture or upload photo");
    }
  }
}

// ==================== MAIN LOOP ====================
void loop() {
  // Handle HTTP server requests
  server.handleClient();
  
  // Check WiFi connection
  static unsigned long lastWiFiCheck = 0;
  if (millis() - lastWiFiCheck > 30000) {
    lastWiFiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️  WiFi disconnected, reconnecting...");
      WiFi.reconnect();
    }
  }
  
  // Maintain MQTT connection
  if (!mqttClient.connected()) {
    reconnectMQTT();
  }
  mqttClient.loop();
  
  // Publish status every 60 seconds
  static unsigned long lastStatusPublish = 0;
  if (millis() - lastStatusPublish > 60000) {
    lastStatusPublish = millis();
    publishStatus("online");
  }
}

// ==================== WEB SERVER HANDLERS ====================
void handleRoot() {
  String html = "<!DOCTYPE html><html><head><title>ESP32-CAM IntelliLock</title>";
  html += "<meta name='viewport' content='width=device-width, initial-scale=1'>";
  html += "<style>";
  html += "body { font-family: Arial; text-align: center; padding: 50px; background: #f0f0f0; }";
  html += "h1 { color: #333; }";
  html += ".status { color: green; font-weight: bold; font-size: 20px; }";
  html += "button { padding: 15px 30px; font-size: 18px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 10px; }";
  html += "button:hover { background: #45a049; }";
  html += ".info { margin: 20px; padding: 15px; background: white; border-radius: 5px; }";
  html += ".mqtt-status { padding: 10px; margin: 10px; border-radius: 5px; }";
  html += ".connected { background: #d4edda; color: #155724; }";
  html += ".disconnected { background: #f8d7da; color: #721c24; }";
  html += "</style></head>";
  html += "<body>";
  html += "<h1>📷 ESP32-CAM IntelliLock</h1>";
  html += "<div class='info'>";
  html += "<p>HTTP Status: <span class='status'>Active ✅</span></p>";
  
  // MQTT Status
  if (mqttClient.connected()) {
    html += "<div class='mqtt-status connected'>MQTT: Connected ✅</div>";
  } else {
    html += "<div class='mqtt-status disconnected'>MQTT: Disconnected ❌</div>";
  }
  
  html += "<p>IP Address: " + WiFi.localIP().toString() + "</p>";
  html += "<p>MQTT Broker: " + String(mqtt_server) + ":" + String(mqtt_port) + "</p>";
  html += "</div>";
  html += "<p><a href='/capture'><button>📸 Capture & Upload Photo (HTTP)</button></a></p>";
  html += "<p style='color: #666; font-size: 14px;'>Camera can be triggered via:</p>";
  html += "<p style='color: #666; font-size: 14px;'>• MQTT Topic: " + String(TOPIC_CAMERA_TRIGGER) + "</p>";
  html += "<p style='color: #666; font-size: 14px;'>• HTTP: /capture endpoint</p>";
  html += "</body></html>";
  server.send(200, "text/html", html);
}

void handleCapture() {
  Serial.println("\n📸 HTTP Capture request received...");
  
  if (captureAndUpload("HTTP_MANUAL")) {
    server.send(200, "application/json", "{\"status\":\"success\",\"message\":\"Photo captured and uploaded\"}");
  } else {
    server.send(500, "application/json", "{\"status\":\"error\",\"message\":\"Failed to capture photo\"}");
  }
}

// ==================== CAPTURE AND UPLOAD ====================
bool captureAndUpload(String userInfo) {
  // Turn on flash LED
  digitalWrite(FLASH_LED_PIN, HIGH);
  delay(100);  // Give time for flash to stabilize
  
  // Capture photo
  camera_fb_t * fb = esp_camera_fb_get();
  
  // Turn off flash LED
  digitalWrite(FLASH_LED_PIN, LOW);
  
  if (!fb) {
    Serial.println("❌ Camera capture failed");
    return false;
  }
  
  Serial.printf("📷 Photo captured: %d bytes\n", fb->len);
  
  // Upload to Laravel
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(uploadURL);
    
    // Add custom headers with user info
    http.addHeader("Content-Type", "image/jpeg");
    if (userInfo != "") {
      http.addHeader("X-User-ID", userInfo);
    }
    http.addHeader("X-Timestamp", String(millis()));
    http.setTimeout(15000);  // 15 second timeout
    
    int httpCode = http.POST(fb->buf, fb->len);
    
    if (httpCode == 200) {
      Serial.println("✅ Photo uploaded successfully");
      String response = http.getString();
      Serial.println("📡 Server response: " + response);
      http.end();
      esp_camera_fb_return(fb);
      return true;
    } else {
      Serial.printf("❌ Upload failed with HTTP code: %d\n", httpCode);
      if (httpCode > 0) {
        String response = http.getString();
        Serial.println("Server response: " + response);
      }
      http.end();
      esp_camera_fb_return(fb);
      return false;
    }
  } else {
    Serial.println("⚠️  WiFi not connected, cannot upload");
    esp_camera_fb_return(fb);
    return false;
  }
}

// ==================== PUBLISH STATUS ====================
void publishStatus(String status) {
  if (!mqttClient.connected()) {
    return;
  }

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

// ==================== PUBLISH RESULT ====================
void publishResult(bool success, String message) {
  if (!mqttClient.connected()) {
    return;
  }

  StaticJsonDocument<200> doc;
  doc["success"] = success;x
  doc["message"] = message;
  doc["timestamp"] = millis();

  char jsonBuffer[200];
  serializeJson(doc, jsonBuffer);

  mqttClient.publish(TOPIC_CAMERA_RESULT, jsonBuffer);
  Serial.println("📡 Result published to MQTT");
}