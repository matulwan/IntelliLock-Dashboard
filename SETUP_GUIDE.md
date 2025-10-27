# 🔐 Intelli-Lock Dashboard - Complete Setup Guide

## 📋 Table of Contents
1. [System Overview](#system-overview)
2. [Prerequisites](#prerequisites)
3. [Laravel Backend Setup](#laravel-backend-setup)
4. [Database Configuration](#database-configuration)
5. [ESP32 Hardware Setup](#esp32-hardware-setup)
6. [ESP32 Software Setup](#esp32-software-setup)
7. [Testing the System](#testing-the-system)
8. [Troubleshooting](#troubleshooting)
9. [API Reference](#api-reference)

---

## 🎯 System Overview

The Intelli-Lock Dashboard is a complete IoT key management system that tracks:
- **User Authentication** via RFID cards and fingerprint sensors
- **Key Checkout/Checkin** with automatic logging
- **Photo Capture** from ESP32-CAM on access events
- **Real-time Alerts** for security issues (door left open, unauthorized access)
- **System Monitoring** with device status and statistics

### IoT Workflow
```
1. User taps RFID/scans fingerprint → Box unlocks
2. ESP32-CAM captures photo → Uploads to Laravel
3. User takes key (with RFID tag)
4. User taps key RFID tag → System logs checkout
5. User returns key → Taps RFID tag → System logs checkin
6. If errors occur → Buzzer alerts + Laravel notification
```

---

## 📦 Prerequisites

### Software Requirements
- **PHP** >= 8.2
- **Composer** (latest version)
- **Node.js** >= 18.x and npm
- **MySQL** >= 8.0 or **MariaDB** >= 10.3
- **Arduino IDE** with ESP32 board support

### Hardware Requirements
- ESP32 DevKit (or ESP32-CAM with programmer)
- MFRC522 RFID Reader
- AS608/R307 Fingerprint Sensor
- 16x2 LCD with I2C backpack
- Solenoid lock (5V or 12V with relay)
- Buzzer (active or passive)
- Magnetic door sensor (optional)
- RFID cards/tags for users and keys

---

## 🚀 Laravel Backend Setup

### Step 1: Clone and Install Dependencies

```bash
cd c:\xampp\htdocs\lara-react-crud

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### Step 2: Environment Configuration

```bash
# Copy environment file
copy .env.production.example .env

# Generate application key
php artisan key:generate
```

### Step 3: Configure .env File

Edit `.env` and update these critical settings:

```env
APP_NAME="Intelli-Lock Dashboard"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intellilock_db
DB_USERNAME=root
DB_PASSWORD=your_password_here

# Filesystem
FILESYSTEM_DISK=public
```

---

## 🗄️ Database Configuration

### Step 1: Create Database

**Using MySQL Command Line:**
```sql
CREATE DATABASE intellilock_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Or using phpMyAdmin:**
1. Open http://localhost/phpmyadmin
2. Click "New" to create database
3. Name: `intellilock_db`
4. Collation: `utf8mb4_unicode_ci`

### Step 2: Run Migrations

```bash
# Run all migrations to create tables
php artisan migrate

# If you need to reset and start fresh
php artisan migrate:fresh
```

This creates the following tables:
- `users` - System users with RFID/fingerprint data
- `lab_keys` - Physical keys with RFID tags
- `key_transactions` - Checkout/checkin history
- `access_logs` - All access attempts
- `event_photos` - Photos from ESP32-CAM
- `system_alerts` - Error and alert notifications
- `iot_devices` - Connected ESP32 devices

### Step 3: Seed Sample Data (Optional)

```bash
# Create sample users and keys for testing
php artisan db:seed
```

### Step 4: Configure Storage

```bash
# Create symbolic link for public storage
php artisan storage:link

# Create photos directory
mkdir storage\app\public\photos

# Set permissions (Windows - run as Administrator)
icacls storage /grant Users:F /T
icacls bootstrap\cache /grant Users:F /T
```

---

## 🔧 ESP32 Hardware Setup

### Pin Connections

#### RFID Reader (MFRC522)
```
ESP32 Pin    → RFID Pin
GPIO 15      → SDA/SS
GPIO 13      → RST
GPIO 18      → SCK
GPIO 23      → MOSI
GPIO 19      → MISO
3.3V         → VCC
GND          → GND
```

#### Fingerprint Sensor (AS608)
```
ESP32 Pin    → Sensor Pin
GPIO 25      → TX (sensor RX)
GPIO 26      → RX (sensor TX)
5V           → VCC (red wire)
GND          → GND (black wire)
```

#### LCD Display (16x2 I2C)
```
ESP32 Pin    → LCD Pin
GPIO 21      → SDA
GPIO 22      → SCL
5V           → VCC
GND          → GND
```

#### Solenoid Lock & Buzzer
```
ESP32 Pin    → Component
GPIO 14      → Relay IN (for solenoid)
GPIO 27      → Buzzer (+)
5V           → Relay VCC
GND          → Relay GND, Buzzer (-)
```

#### Optional Door Sensor
```
ESP32 Pin    → Sensor
GPIO 33      → Magnetic sensor signal
GND          → Sensor GND
```

### Power Supply
- ESP32: 5V via USB or VIN pin
- Solenoid: Separate 12V power supply (if using 12V solenoid)
- Total current: ~2A recommended

---

## 💻 ESP32 Software Setup

### Step 1: Install Arduino IDE Libraries

Open Arduino IDE → Tools → Manage Libraries, install:
- `MFRC522` by GithubCommunity
- `Adafruit Fingerprint Sensor Library`
- `LiquidCrystal I2C` by Frank de Brabander
- `ArduinoJson` by Benoit Blanchon (v6.x)

### Step 2: Configure ESP32 Board

1. File → Preferences → Additional Board Manager URLs:
   ```
   https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
   ```

2. Tools → Board → Boards Manager → Search "ESP32" → Install

3. Tools → Board → ESP32 Arduino → Select your board (e.g., "ESP32 Dev Module")

### Step 3: Update ESP32 Code

Open `esp32_key_box_code_updated.ino` and update:

```cpp
// WiFi Configuration
const char *ssid = "YOUR_WIFI_SSID";
const char *password = "YOUR_WIFI_PASSWORD";

// Laravel API (use your computer's IP address)
const char *LARAVEL_API_BASE = "http://192.168.1.100:8000/api/iot";
```

**To find your computer's IP:**
```bash
# Windows
ipconfig

# Look for "IPv4 Address" under your active network adapter
# Example: 192.168.1.100
```

### Step 4: Upload Code

1. Connect ESP32 via USB
2. Tools → Port → Select COM port
3. Tools → Upload Speed → 115200
4. Click Upload button
5. Wait for "Done uploading"

### Step 5: Monitor Serial Output

1. Tools → Serial Monitor
2. Set baud rate to 115200
3. Watch for connection status and API responses

---

## 🧪 Testing the System

### Step 1: Start Laravel Server

```bash
# Start development server
php artisan serve --host=0.0.0.0 --port=8000

# In another terminal, start Vite for frontend
npm run dev
```

### Step 2: Access Dashboard

Open browser: http://localhost:8000

Default login (if seeded):
- Email: admin@intellilock.local
- Password: password

### Step 3: Add Test Users

1. Navigate to **User Management**
2. Click **Add User**
3. Fill in details:
   - Name: Test User
   - Email: test@example.com
   - RFID UID: Scan a card and copy the UID from Serial Monitor
   - IoT Access: Enable
4. Save

### Step 4: Test RFID Authentication

1. Present RFID card to reader
2. Check Serial Monitor for authentication request
3. LCD should show "Access Granted" or "Access Denied"
4. Dashboard should log the access attempt

### Step 5: Test Key Transaction

1. Authenticate with RFID/fingerprint
2. Box unlocks
3. Scan a key's RFID tag
4. Check dashboard for transaction log

### Step 6: Test Alerts

1. Unlock box
2. Wait 15 seconds without closing
3. Buzzer should sound
4. Check dashboard for alert notification

---

## 🔍 Troubleshooting

### ESP32 Won't Connect to WiFi

**Problem:** ESP32 shows "WiFi Connection Failed"

**Solutions:**
- Verify SSID and password are correct
- Ensure WiFi is 2.4GHz (ESP32 doesn't support 5GHz)
- Check WiFi signal strength
- Restart router if needed

### API Communication Errors

**Problem:** ESP32 shows "Server Error" or HTTP 404/500

**Solutions:**
```bash
# Check Laravel is running
php artisan serve --host=0.0.0.0 --port=8000

# Check firewall allows port 8000
# Windows: Settings → Firewall → Allow app

# Verify IP address in ESP32 code matches server IP
ipconfig

# Check Laravel logs
tail -f storage/logs/laravel.log
```

### RFID Not Reading Cards

**Problem:** No response when presenting RFID card

**Solutions:**
- Check wiring connections (especially SDA, SCK, MOSI, MISO)
- Verify 3.3V power supply to RFID module
- Test with known working RFID cards
- Check Serial Monitor for initialization messages

### Fingerprint Sensor Not Working

**Problem:** "Fingerprint sensor not found" error

**Solutions:**
- Verify TX/RX connections (TX→RX, RX→TX)
- Check 5V power supply
- Verify baud rate is 57600
- Test sensor LED (should glow when powered)

### Photos Not Uploading

**Problem:** ESP32-CAM photos fail to upload

**Solutions:**
```bash
# Verify storage link exists
php artisan storage:link

# Check directory permissions
icacls storage\app\public\photos /grant Users:F

# Verify .env setting
FILESYSTEM_DISK=public

# Check max upload size in php.ini
upload_max_filesize = 10M
post_max_size = 10M
```

### Database Connection Failed

**Problem:** Laravel shows "Database connection error"

**Solutions:**
```bash
# Verify MySQL is running
# Check XAMPP Control Panel → MySQL → Start

# Test database connection
php artisan tinker
DB::connection()->getPdo();

# Check .env database credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intellilock_db
DB_USERNAME=root
DB_PASSWORD=

# Clear config cache
php artisan config:clear
php artisan cache:clear
```

---

## 📡 API Reference

### Authentication Endpoint
```http
POST /api/iot/authenticate
Content-Type: application/json

{
  "terminal": "lab_key_box",
  "type": "rfid",
  "uid": "A1B2C3D4"
}

Response:
{
  "status": "success",
  "access": "door",
  "name": "John Doe",
  "message": "Access Granted",
  "door_action": "open"
}
```

### Key Transaction Endpoint
```http
POST /api/iot/key-transaction
Content-Type: application/json

{
  "key_rfid_uid": "E5F6G7H8",
  "action": "checkout",
  "device": "lab_key_box",
  "user_rfid_uid": "A1B2C3D4"
}

Response:
{
  "status": "success",
  "message": "Key Lab A checkout logged",
  "key": "Lab A",
  "action": "checkout"
}
```

### Photo Upload Endpoint
```http
POST /api/iot/upload-photo
Content-Type: multipart/form-data

photo: [binary image file]
device: "lab_key_box"
event_type: "access"
access_log_id: 123

Response:
{
  "status": "success",
  "message": "Photo uploaded successfully",
  "photo_id": 45,
  "photo_url": "http://localhost:8000/storage/photos/1234567890_lab_key_box_abc.jpg"
}
```

### Alert Endpoint
```http
POST /api/iot/alert
Content-Type: application/json

{
  "device": "lab_key_box",
  "alert_type": "door_left_open",
  "severity": "high",
  "title": "Door Left Open",
  "description": "User John Doe left the door open",
  "user_name": "John Doe"
}

Response:
{
  "status": "success",
  "message": "Alert created successfully",
  "alert_id": 12
}
```

### System Status Endpoint
```http
GET /api/iot/status?device=lab_key_box

Response:
{
  "status": "success",
  "device": {
    "name": "lab_key_box",
    "status": "online",
    "last_seen": "2025-10-27T14:30:00Z",
    "ip_address": "192.168.1.150"
  },
  "keys": {
    "total": 5,
    "available": 3,
    "checked_out": 2
  },
  "alerts": {
    "active": 1
  },
  "access": {
    "today": 15
  },
  "server_time": "2025-10-27T14:35:22+00:00"
}
```

### Heartbeat Endpoint
```http
POST /api/iot/heartbeat
Content-Type: application/json

{
  "terminal": "lab_key_box",
  "timestamp": 1234567890,
  "box_status": "locked"
}

Response:
{
  "status": "ok",
  "server_time": 1234567890,
  "message": "Heartbeat received"
}
```

---

## 🎓 Additional Resources

### Dashboard Pages
- **Overview** (`/`) - System statistics and recent activity
- **Access Logs** (`/access-logs`) - All access attempts
- **User Management** (`/user-management`) - Manage authorized users
- **IoT Devices** (`/iot-devices`) - Monitor ESP32 devices

### Database Schema
All tables are created automatically by migrations. Key tables:
- `users` - Stores user credentials and IoT access data
- `lab_keys` - Physical keys with RFID tags
- `key_transactions` - Complete checkout/checkin history
- `access_logs` - Every access attempt (success/denied)
- `event_photos` - Photos captured by ESP32-CAM
- `system_alerts` - Security alerts and errors

### Security Best Practices
1. Change default passwords in production
2. Use HTTPS for API communication
3. Implement API rate limiting
4. Regular database backups
5. Secure ESP32 device physically
6. Use strong WiFi encryption (WPA2/WPA3)

---

## 📞 Support

For issues or questions:
1. Check Serial Monitor output from ESP32
2. Review Laravel logs: `storage/logs/laravel.log`
3. Verify network connectivity
4. Test individual components separately

---

**System Version:** 2.0  
**Last Updated:** October 27, 2025  
**License:** MIT
