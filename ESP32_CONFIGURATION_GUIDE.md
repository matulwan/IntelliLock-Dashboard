# 🔧 ESP32 Configuration Guide for Your Intelli-Lock Code

## 📋 Overview

This guide shows you how to configure your ESP32 code to work with the Laravel dashboard.

---

## ⚙️ ESP32 Code Configuration

### 1. Update WiFi Credentials

```cpp
// WiFi credentials
const char* ssid = "YOUR_WIFI_NAME";        // Change this
const char* password = "YOUR_WIFI_PASSWORD"; // Change this
```

**Example:**
```cpp
const char* ssid = "MyHomeWiFi";
const char* password = "MySecurePassword123";
```

### 2. Update Laravel API URL

First, find your computer's IP address:

**Windows:**
```bash
ipconfig
# Look for "IPv4 Address" under your active network adapter
# Example: 192.168.1.100
```

**Mac/Linux:**
```bash
ifconfig
# or
ip addr show
```

Then update the ESP32 code:

```cpp
// Laravel endpoint
const char* apiURL = "http://192.168.1.100:8000/api/intellilock/event";
//                          ^^^^^^^^^^^^^ Your computer's IP address
```

**Important:** Replace `your-laravel-dashboard.local` with your actual IP address!

---

## 🔌 API Endpoints Your ESP32 Uses

### Main Event Endpoint
```
POST http://YOUR_IP:8000/api/intellilock/event
```

**Request Format:**
```json
{
  "action": "door_unlocked",
  "extra": ""
}
```

**Actions Sent by ESP32:**

| Action | When It's Sent | Extra Data |
|--------|----------------|------------|
| `door_unlocked` | Fingerprint or RFID opens door | None |
| `fingerprint_match` | Fingerprint verified | None |
| `key_tagged_taken` | First RFID tap (checkout) | Key RFID UID |
| `key_tagged_returned` | Second RFID tap (checkin) | Key RFID UID |
| `door_timeout_alert` | Door left open > 20 seconds | None |

### Photo Upload Endpoint (ESP32-CAM)
```
POST http://YOUR_IP:8000/api/intellilock/upload
```

**Request Format:**
```
Content-Type: multipart/form-data
photo: [binary image file]
event_type: "access" (optional)
```

### Status Check Endpoint
```
GET http://YOUR_IP:8000/api/intellilock/status
```

---

## 🧪 Testing Your Configuration

### Step 1: Start Laravel Server

```bash
cd c:\xampp\htdocs\lara-react-crud
php artisan serve --host=0.0.0.0 --port=8000
```

You should see:
```
Laravel development server started: http://0.0.0.0:8000
```

### Step 2: Test API Endpoint

Open a new terminal and test:

```bash
curl -X POST http://localhost:8000/api/intellilock/event ^
  -H "Content-Type: application/json" ^
  -d "{\"action\":\"door_unlocked\",\"extra\":\"\"}"
```

Expected response:
```json
{
  "status": "success",
  "message": "Door unlock logged",
  "action": "door_unlocked"
}
```

### Step 3: Upload ESP32 Code

1. Open Arduino IDE
2. Load your ESP32 code
3. Update WiFi credentials and API URL
4. Select correct board: Tools → Board → ESP32 Dev Module
5. Select correct port: Tools → Port → COM# (your ESP32 port)
6. Click Upload

### Step 4: Monitor Serial Output

1. Tools → Serial Monitor
2. Set baud rate to 115200
3. Press ESP32 reset button

You should see:
```
Connecting WiFi.....
✅ WiFi Connected
✅ Fingerprint ready
```

### Step 5: Test RFID Scan

1. Scan an RFID card
2. Watch Serial Monitor for:
```
RFID UID: a1b2c3d4
📡 Sent: {"action":"key_tagged_taken","extra":"a1b2c3d4"} | Code: 200
```

3. Check Laravel dashboard for the transaction

---

## 🔄 Complete Workflow Test

### Test 1: Door Unlock with Fingerprint

1. Place finger on sensor
2. Serial Monitor shows:
```
Fingerprint match: ID=1
📡 Sent: {"action":"fingerprint_match","extra":""} | Code: 200
🔓 Door Unlocked
📷 Triggering ESP32-CAM
📡 Sent: {"action":"door_unlocked","extra":""} | Code: 200
```

3. Check dashboard → Access Logs → Should show fingerprint access

### Test 2: Key Checkout

1. After door unlocks, scan a key's RFID tag
2. Serial Monitor shows:
```
RFID UID: key123abc
📡 Sent: {"action":"key_tagged_taken","extra":"key123abc"} | Code: 200
```

3. Check dashboard → Key Transactions → Should show checkout

### Test 3: Key Return

1. Scan the same key RFID tag again
2. Serial Monitor shows:
```
RFID UID: key123abc
🔒 Door Locked
📡 Sent: {"action":"key_tagged_returned","extra":"key123abc"} | Code: 200
```

3. Check dashboard → Key Transactions → Should show checkin
4. Key status should change to "available"

### Test 4: Timeout Alert

1. Unlock door (fingerprint or RFID)
2. Wait 20 seconds without scanning a key
3. Buzzer should beep 5 times
4. Serial Monitor shows:
```
⚠️ Timeout: door left open or tag not scanned
📡 Sent: {"action":"door_timeout_alert","extra":""} | Code: 200
```

5. Check dashboard → Alerts → Should show timeout alert

---

## 🐛 Troubleshooting

### Problem: WiFi Won't Connect

**Symptoms:**
```
Connecting WiFi.................
```
(Never connects)

**Solutions:**
1. Verify SSID and password are correct
2. Ensure WiFi is 2.4GHz (ESP32 doesn't support 5GHz)
3. Check WiFi signal strength at ESP32 location
4. Try restarting router

### Problem: HTTP Error Code 404

**Symptoms:**
```
📡 Sent: {"action":"door_unlocked","extra":""} | Code: 404
```

**Solutions:**
1. Verify Laravel server is running: `php artisan serve --host=0.0.0.0 --port=8000`
2. Check API URL in ESP32 code matches your server IP
3. Test endpoint manually with curl
4. Check Laravel routes: `php artisan route:list | findstr intellilock`

### Problem: HTTP Error Code 500

**Symptoms:**
```
📡 Sent: {"action":"key_tagged_taken","extra":"abc123"} | Code: 500
```

**Solutions:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify database is running
3. Run migrations: `php artisan migrate`
4. Check database connection in `.env`

### Problem: Fingerprint Sensor Not Found

**Symptoms:**
```
❌ Fingerprint not found
```

**Solutions:**
1. Check wiring: TX→RX, RX→TX (crossed)
2. Verify 5V power supply
3. Check baud rate is 57600
4. Test sensor LED (should glow when powered)

### Problem: RFID Not Reading

**Symptoms:**
- No response when scanning cards

**Solutions:**
1. Check SPI wiring (SS_PIN=15, RST_PIN=13)
2. Verify 3.3V power to RFID module
3. Test with known working RFID cards
4. Check Serial Monitor for initialization message

---

## 📊 Expected Serial Monitor Output

### Normal Operation:
```
Connecting WiFi.....
✅ WiFi Connected
✅ Fingerprint ready

Fingerprint match: ID=1
📡 Sent: {"action":"fingerprint_match","extra":""} | Code: 200
🔓 Door Unlocked
📷 Triggering ESP32-CAM
📡 Sent: {"action":"door_unlocked","extra":""} | Code: 200

RFID UID: abc123
📡 Sent: {"action":"key_tagged_taken","extra":"abc123"} | Code: 200

RFID UID: abc123
🔒 Door Locked
📡 Sent: {"action":"key_tagged_returned","extra":"abc123"} | Code: 200
```

### With Timeout Alert:
```
🔓 Door Unlocked
📷 Triggering ESP32-CAM
📡 Sent: {"action":"door_unlocked","extra":""} | Code: 200

⚠️ Timeout: door left open or tag not scanned
📡 Sent: {"action":"door_timeout_alert","extra":""} | Code: 200
```

---

## 🔐 Security Notes

1. **WiFi Security:** Use WPA2/WPA3 encryption
2. **Network:** Keep ESP32 and server on same local network
3. **Firewall:** Allow port 8000 for local network
4. **Production:** Use HTTPS and proper authentication in production

---

## 📝 Quick Reference

### ESP32 Pins Used:
- **GPIO 15** - RFID SS
- **GPIO 13** - RFID RST
- **GPIO 14** - Relay (Solenoid)
- **GPIO 27** - Buzzer
- **GPIO 32** - ESP32-CAM Trigger
- **GPIO 25** - Fingerprint TX
- **GPIO 26** - Fingerprint RX

### API Endpoints:
- `POST /api/intellilock/event` - Main event handler
- `POST /api/intellilock/upload` - Photo upload
- `GET /api/intellilock/status` - System status

### Timeout Settings:
- Door unlock duration: 5 seconds
- Timeout before alert: 20 seconds
- Buzzer beeps: 5 times (200ms on/off)

---

## ✅ Configuration Checklist

Before deploying, verify:

- [ ] WiFi SSID and password updated
- [ ] Laravel API URL updated with correct IP
- [ ] Laravel server running on port 8000
- [ ] Database migrations run
- [ ] Storage link created
- [ ] ESP32 code uploaded successfully
- [ ] WiFi connection established
- [ ] Fingerprint sensor initialized
- [ ] RFID reader working
- [ ] Test event sent successfully (HTTP 200)
- [ ] Dashboard shows events

---

**Need Help?** Check `SETUP_GUIDE.md` for detailed troubleshooting or run `php test_iot_api.php` to test API endpoints.
