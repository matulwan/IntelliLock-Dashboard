# 🚀 Intelli-Lock Dashboard - Quick Start Guide

Get your Intelli-Lock system up and running in 15 minutes!

## ⚡ Prerequisites Check

Before starting, make sure you have:
- ✅ XAMPP installed (or PHP 8.2+ and MySQL)
- ✅ Composer installed
- ✅ Node.js and npm installed
- ✅ Arduino IDE with ESP32 support (for hardware)

## 📝 Step-by-Step Setup

### 1️⃣ Database Setup (2 minutes)

```bash
# Start MySQL in XAMPP Control Panel
# Then create database
```

**Option A: Using phpMyAdmin**
1. Open http://localhost/phpmyadmin
2. Click "New" → Database name: `intellilock_db`
3. Click "Create"

**Option B: Using MySQL Command Line**
```sql
CREATE DATABASE intellilock_db;
```

### 2️⃣ Laravel Configuration (3 minutes)

```bash
# Navigate to project directory
cd c:\xampp\htdocs\lara-react-crud

# Install dependencies
composer install
npm install

# Copy environment file
copy .env.production.example .env

# Generate application key
php artisan key:generate
```

### 3️⃣ Update .env File (1 minute)

Edit `.env` and set:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intellilock_db
DB_USERNAME=root
DB_PASSWORD=
```

### 4️⃣ Run Migrations (1 minute)

```bash
# Create all database tables
php artisan migrate

# Create storage link for photos
php artisan storage:link

# Optional: Seed sample data
php artisan db:seed
```

### 5️⃣ Start Laravel Server (1 minute)

```bash
# Terminal 1: Start Laravel
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Start Vite (for frontend)
npm run dev
```

### 6️⃣ Test the Dashboard (2 minutes)

1. Open browser: http://localhost:8000
2. You should see the Intelli-Lock Dashboard
3. Navigate through pages to verify everything works

### 7️⃣ Test API Endpoints (2 minutes)

```bash
# Run API test script
php test_iot_api.php
```

Expected output: All tests should pass (or most of them)

### 8️⃣ Configure ESP32 (3 minutes)

1. Open `esp32_key_box_code_updated.ino` in Arduino IDE

2. Update WiFi credentials:
```cpp
const char *ssid = "YOUR_WIFI_NAME";
const char *password = "YOUR_WIFI_PASSWORD";
```

3. Find your computer's IP address:
```bash
# Windows
ipconfig
# Look for IPv4 Address, e.g., 192.168.1.100
```

4. Update API URL:
```cpp
const char *LARAVEL_API_BASE = "http://192.168.1.100:8000/api/iot";
```

5. Upload to ESP32

---

## ✅ Verification Checklist

After setup, verify these work:

- [ ] Dashboard loads at http://localhost:8000
- [ ] Database has tables (check phpMyAdmin)
- [ ] API test script passes most tests
- [ ] ESP32 connects to WiFi (check Serial Monitor)
- [ ] ESP32 can reach Laravel API
- [ ] RFID reader initializes
- [ ] LCD displays messages

---

## 🎯 Quick Test Workflow

### Test 1: Add a Test User

1. Go to **User Management** → **Add User**
2. Fill in:
   - Name: `Test User`
   - Email: `test@test.com`
   - RFID UID: `A1B2C3D4` (or scan actual card)
   - IoT Access: ✅ Enable
3. Save

### Test 2: Add a Test Key

1. Go to **Lab Keys** (if available) or use database
2. Add key:
   - Key Name: `Lab A`
   - RFID UID: `KEY001`
   - Status: `available`

### Test 3: Test Authentication

**Using API Test Script:**
```bash
php test_iot_api.php
```

**Using ESP32:**
1. Present RFID card to reader
2. Check Serial Monitor for response
3. LCD should show "Access Granted" or "Access Denied"

### Test 4: Test Key Transaction

1. Authenticate with RFID
2. Box unlocks
3. Scan key RFID tag
4. Check dashboard for transaction log

---

## 🔧 Common Issues & Quick Fixes

### Issue: "Database connection failed"
```bash
# Fix: Check MySQL is running
# XAMPP Control Panel → MySQL → Start

# Verify .env settings
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=intellilock_db
```

### Issue: "Class not found" errors
```bash
# Fix: Clear cache and regenerate autoload
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Issue: ESP32 won't connect to WiFi
```
Fix:
1. Verify SSID and password are correct
2. Ensure WiFi is 2.4GHz (not 5GHz)
3. Check WiFi signal strength
4. Restart ESP32
```

### Issue: "Server Error" from ESP32
```
Fix:
1. Verify Laravel is running: php artisan serve
2. Check firewall allows port 8000
3. Verify IP address in ESP32 code matches server
4. Check Laravel logs: storage/logs/laravel.log
```

### Issue: Photos not uploading
```bash
# Fix: Ensure storage is linked
php artisan storage:link

# Create photos directory
mkdir storage\app\public\photos

# Set permissions (Windows - run as Admin)
icacls storage /grant Users:F /T
```

---

## 📊 System Architecture

```
┌─────────────┐
│   ESP32     │  ← RFID Reader, Fingerprint, LCD, Solenoid
│  (IoT Box)  │
└──────┬──────┘
       │ WiFi
       │ HTTP POST/GET
       ▼
┌─────────────────┐
│  Laravel API    │  ← Routes: /api/iot/*
│  (Backend)      │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│  MySQL Database │  ← Tables: users, keys, logs, photos, alerts
└─────────────────┘
       ▲
       │
┌──────┴──────────┐
│  React Dashboard│  ← UI: Overview, Logs, Users, Devices
│  (Frontend)     │
└─────────────────┘
```

---

## 📚 Next Steps

Once basic setup works:

1. **Add Real Users**: Import actual RFID card UIDs
2. **Configure Keys**: Add all physical keys with their RFID tags
3. **Test Alerts**: Simulate error scenarios
4. **Setup ESP32-CAM**: For photo capture (optional)
5. **Customize Dashboard**: Modify frontend as needed
6. **Security**: Change default passwords, enable HTTPS

---

## 📖 Full Documentation

For detailed information, see:
- **SETUP_GUIDE.md** - Complete setup instructions
- **ESP32_INTEGRATION_SETUP.md** - Hardware wiring and configuration
- **API Reference** - In SETUP_GUIDE.md

---

## 🆘 Need Help?

1. Check Serial Monitor output (ESP32)
2. Check Laravel logs: `storage/logs/laravel.log`
3. Run API test: `php test_iot_api.php`
4. Verify network connectivity
5. Review SETUP_GUIDE.md for detailed troubleshooting

---

**Estimated Total Setup Time:** 15-20 minutes  
**Difficulty Level:** Intermediate  
**System Status:** Production Ready ✅
