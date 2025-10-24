# ESP32 IoT Integration Setup Guide

This guide will help you integrate your ESP32 access control system with the Laravel React dashboard.

## 📋 Prerequisites

- ESP32 development board
- RFID-RC522 module
- Fingerprint sensor (AS608/R307)
- 16x2 LCD with I2C backpack
- Relay module for door control
- Arduino IDE with ESP32 board support
- Laravel application running on local network

## 🔧 Hardware Setup

### Pin Connections

```
ESP32 Pin    | Component        | Connection
-------------|------------------|------------------
GPIO 15      | RFID RC522       | SDA/SS
GPIO 13      | RFID RC522       | RST
GPIO 18      | RFID RC522       | SCK
GPIO 23      | RFID RC522       | MOSI
GPIO 19      | RFID RC522       | MISO
3.3V         | RFID RC522       | VCC
GND          | RFID RC522       | GND
-------------|------------------|------------------
GPIO 21      | LCD I2C          | SDA
GPIO 22      | LCD I2C          | SCL
5V           | LCD I2C          | VCC
GND          | LCD I2C          | GND
-------------|------------------|------------------
GPIO 25      | Fingerprint      | TX (to sensor RX)
GPIO 26      | Fingerprint      | RX (to sensor TX)
5V           | Fingerprint      | VCC
GND          | Fingerprint      | GND
-------------|------------------|------------------
GPIO 14      | Relay Module     | IN
5V           | Relay Module     | VCC
GND          | Relay Module     | GND
```

## 💻 Software Setup

### 1. Laravel Backend Setup

1. **Run Migrations:**
```bash
php artisan migrate
```

2. **Seed Test Data:**
```bash
php artisan db:seed --class=IoTDeviceSeeder
php artisan db:seed --class=AuthorizedUserSeeder
```

3. **Install Required Packages:**
```bash
# If not already installed
composer require laravel/sanctum
npm install
npm run build
```

4. **Configure API Routes:**
The API routes are already configured in `/routes/api.php`

### 2. ESP32 Code Setup

1. **Install Required Libraries:**
   - MFRC522 (for RFID)
   - LiquidCrystal_I2C (for LCD)
   - Adafruit_Fingerprint (for fingerprint sensor)
   - ArduinoJson (for API communication)

2. **Update Configuration:**
   Edit the ESP32 code (`esp32_updated_code.ino`) with your settings:

```cpp
// WiFi Configuration
const char *ssid = "YOUR_WIFI_SSID";
const char *password = "YOUR_WIFI_PASSWORD";

// Laravel API Configuration
const char *LARAVEL_API_BASE = "http://YOUR_LARAVEL_IP:8000/api/iot";
// Example: "http://192.168.1.100:8000/api/iot"

// Terminal Configuration
#define TERMINAL_NAME "your_terminal_name"
// Example: "basement", "main_entrance", "office_door"
```

3. **Upload Code:**
   - Connect ESP32 to computer
   - Select correct board and port in Arduino IDE
   - Upload the updated code

### 3. Network Configuration

1. **Find Your Laravel Server IP:**
```bash
# Windows
ipconfig

# Linux/Mac
ifconfig
```

2. **Update ESP32 Code:**
Replace `192.168.1.100` with your actual Laravel server IP address.

3. **Firewall Settings:**
Ensure port 8000 is open on your Laravel server for ESP32 communication.

## 🚀 Testing the Integration

### 1. Start Laravel Server
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Monitor ESP32 Serial Output
- Open Arduino IDE Serial Monitor (115200 baud)
- Watch for connection status and API responses

### 3. Test RFID Access
- Present an RFID card to the reader
- Check Serial Monitor for authentication requests
- Verify access logs appear in Laravel dashboard

### 4. Test Fingerprint Access
- Type "3" in Serial Monitor to enter fingerprint mode
- Place enrolled finger on sensor
- Check for authentication and door control

## 📊 Dashboard Features

### IoT Devices Page (`/iot-devices`)
- View all connected ESP32 devices
- Monitor device status (online/offline)
- Check WiFi signal strength and uptime
- Remote door control
- Device statistics

### Authorized Users Page (`/authorized-users`)
- Manage users with RFID/fingerprint access
- Add/edit/delete authorized users
- Enable/disable user access
- View access statistics

### Access Logs Page (`/access-logs`)
- Real-time access attempt logs
- Success/failure statistics
- Filter by user, device, or date
- Export logs for reporting

## 🔧 API Endpoints

The ESP32 communicates with these Laravel API endpoints:

- `POST /api/iot/authenticate` - Verify RFID/fingerprint access
- `POST /api/iot/access-log` - Log access attempts
- `POST /api/iot/device-status` - Update device status
- `POST /api/iot/heartbeat` - Device health check
- `GET /api/iot/authorized-users/{terminal}` - Get authorized users
- `POST /api/iot/door-control` - Remote door control

## 🛠️ Troubleshooting

### ESP32 Not Connecting to WiFi
- Check SSID and password
- Ensure 2.4GHz WiFi (ESP32 doesn't support 5GHz)
- Check WiFi signal strength

### API Communication Errors
- Verify Laravel server IP address
- Check firewall settings
- Ensure Laravel server is running
- Monitor Laravel logs: `tail -f storage/logs/laravel.log`

### RFID Not Working
- Check wiring connections
- Verify SPI pins configuration
- Test with known working RFID cards

### Fingerprint Sensor Issues
- Check UART connections (TX/RX)
- Verify baud rate (57600)
- Ensure sensor has power (5V)

### Database Issues
- Run migrations: `php artisan migrate`
- Check database connection in `.env`
- Seed test data if needed

## 📱 Mobile Access

The Laravel dashboard is responsive and works on mobile devices for remote monitoring and control.

## 🔒 Security Considerations

1. **Network Security:**
   - Use WPA2/WPA3 WiFi encryption
   - Consider VPN for remote access
   - Implement IP whitelisting if needed

2. **API Security:**
   - Consider adding API authentication tokens
   - Implement rate limiting
   - Use HTTPS in production

3. **Physical Security:**
   - Secure ESP32 device installation
   - Protect wiring from tampering
   - Use tamper-evident enclosures

## 📈 Scaling

To add more ESP32 devices:
1. Flash the same code with different `TERMINAL_NAME`
2. Add device entry in Laravel dashboard
3. Configure authorized users for each terminal
4. Monitor all devices from central dashboard

## 🆘 Support

If you encounter issues:
1. Check Serial Monitor output
2. Review Laravel logs
3. Verify network connectivity
4. Test individual components separately
5. Check hardware connections

The system provides comprehensive logging and monitoring to help diagnose issues quickly.
