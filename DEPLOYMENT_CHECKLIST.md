# 🚀 Intelli-Lock Deployment Checklist

Use this checklist to ensure your Intelli-Lock system is properly configured and ready for production.

## ✅ Pre-Deployment Checklist

### 📦 Laravel Backend

- [ ] **Environment Configuration**
  - [ ] `.env` file created from `.env.production.example`
  - [ ] `APP_KEY` generated (`php artisan key:generate`)
  - [ ] `APP_ENV` set to `production` (for production) or `local` (for development)
  - [ ] `APP_DEBUG` set to `false` (for production)
  - [ ] `APP_URL` configured correctly

- [ ] **Database Configuration**
  - [ ] MySQL/MariaDB installed and running
  - [ ] Database created (`intellilock_db`)
  - [ ] Database credentials in `.env` are correct
  - [ ] Migrations run successfully (`php artisan migrate`)
  - [ ] Database connection tested

- [ ] **Storage Configuration**
  - [ ] Storage link created (`php artisan storage:link`)
  - [ ] Photos directory exists (`storage/app/public/photos/`)
  - [ ] Proper permissions set on `storage/` and `bootstrap/cache/`
  - [ ] File upload limits configured in `php.ini` (5MB+)

- [ ] **Dependencies**
  - [ ] Composer dependencies installed (`composer install --optimize-autoloader --no-dev`)
  - [ ] NPM dependencies installed (`npm install`)
  - [ ] Frontend built (`npm run build`)

- [ ] **Cache & Optimization**
  - [ ] Config cached (`php artisan config:cache`)
  - [ ] Routes cached (`php artisan route:cache`)
  - [ ] Views cached (`php artisan view:cache`)

### 🔧 ESP32 Hardware

- [ ] **Hardware Assembly**
  - [ ] ESP32 board connected
  - [ ] RFID reader (MFRC522) wired correctly
  - [ ] Fingerprint sensor connected
  - [ ] LCD display working
  - [ ] Solenoid lock connected with relay
  - [ ] Buzzer connected
  - [ ] Door sensor installed (optional)
  - [ ] Power supply adequate (2A+ recommended)

- [ ] **Software Configuration**
  - [ ] Arduino IDE installed with ESP32 support
  - [ ] Required libraries installed:
    - [ ] MFRC522
    - [ ] Adafruit_Fingerprint
    - [ ] LiquidCrystal_I2C
    - [ ] ArduinoJson
  - [ ] WiFi credentials configured in code
  - [ ] Laravel API URL configured with correct IP
  - [ ] Device name set (`DEVICE_NAME`)
  - [ ] Code uploaded successfully

- [ ] **Hardware Testing**
  - [ ] ESP32 connects to WiFi
  - [ ] RFID reader detects cards
  - [ ] Fingerprint sensor responds
  - [ ] LCD displays messages
  - [ ] Solenoid lock operates
  - [ ] Buzzer sounds
  - [ ] Serial Monitor shows proper output

### 🌐 Network Configuration

- [ ] **Server Network**
  - [ ] Server IP address is static or reserved in DHCP
  - [ ] Port 8000 is open in firewall
  - [ ] Server accessible from ESP32 network
  - [ ] CORS configured if needed

- [ ] **ESP32 Network**
  - [ ] WiFi is 2.4GHz (ESP32 doesn't support 5GHz)
  - [ ] WiFi signal strength adequate at installation location
  - [ ] Network allows device-to-server communication
  - [ ] No proxy or VPN blocking connections

### 📊 Database Setup

- [ ] **Tables Created**
  - [ ] `users` table exists
  - [ ] `lab_keys` table exists
  - [ ] `key_transactions` table exists
  - [ ] `access_logs` table exists
  - [ ] `event_photos` table exists
  - [ ] `system_alerts` table exists
  - [ ] `iot_devices` table exists

- [ ] **Initial Data**
  - [ ] At least one admin user created
  - [ ] Test users with RFID UIDs added
  - [ ] Physical keys registered with RFID tags
  - [ ] IoT device entry created

### 🧪 Testing

- [ ] **API Testing**
  - [ ] Run `php test_iot_api.php` - all tests pass
  - [ ] Authentication endpoint works
  - [ ] Key transaction endpoint works
  - [ ] Photo upload endpoint works
  - [ ] Alert endpoint works
  - [ ] Status endpoint works
  - [ ] Heartbeat endpoint works

- [ ] **Integration Testing**
  - [ ] ESP32 can authenticate users via RFID
  - [ ] ESP32 can authenticate users via fingerprint
  - [ ] Box unlocks on successful authentication
  - [ ] Key transactions are logged correctly
  - [ ] Alerts are sent when errors occur
  - [ ] Photos upload successfully (if ESP32-CAM used)
  - [ ] Dashboard displays real-time data

- [ ] **User Workflow Testing**
  - [ ] User scans RFID → Box unlocks
  - [ ] User takes key → Scans key RFID → Checkout logged
  - [ ] User returns key → Scans key RFID → Checkin logged
  - [ ] Box auto-locks after timeout
  - [ ] Alerts trigger on errors (door left open, etc.)

### 📱 Dashboard Testing

- [ ] **Pages Load**
  - [ ] Overview page displays statistics
  - [ ] Access Logs page shows history
  - [ ] User Management page works
  - [ ] IoT Devices page shows device status

- [ ] **Functionality**
  - [ ] Can add/edit/delete users
  - [ ] Can view access logs with filters
  - [ ] Can see real-time device status
  - [ ] Charts and statistics display correctly
  - [ ] Photos display in gallery (if applicable)

### 🔒 Security

- [ ] **Authentication & Authorization**
  - [ ] Dashboard login required
  - [ ] Default passwords changed
  - [ ] User roles configured properly
  - [ ] API endpoints validate input

- [ ] **Data Protection**
  - [ ] Database credentials secured
  - [ ] `.env` file not in version control
  - [ ] File permissions set correctly
  - [ ] HTTPS enabled (for production)

- [ ] **Physical Security**
  - [ ] ESP32 device secured in enclosure
  - [ ] Wiring protected from tampering
  - [ ] Solenoid lock properly installed
  - [ ] Access to hardware restricted

## 🎯 Post-Deployment

### Monitoring

- [ ] Check Laravel logs regularly (`storage/logs/laravel.log`)
- [ ] Monitor ESP32 Serial output during initial days
- [ ] Review access logs for anomalies
- [ ] Check system alerts dashboard
- [ ] Monitor device heartbeat status

### Maintenance

- [ ] Schedule regular database backups
- [ ] Plan for log rotation/cleanup
- [ ] Keep Laravel and dependencies updated
- [ ] Update ESP32 firmware as needed
- [ ] Clean old photos periodically

### Documentation

- [ ] Document custom configurations
- [ ] Keep list of registered RFID UIDs
- [ ] Maintain user access list
- [ ] Document network configuration
- [ ] Create user training materials

## 🆘 Emergency Procedures

### If System Goes Down

1. Check Laravel server is running
2. Check MySQL is running
3. Check ESP32 WiFi connection
4. Review error logs
5. Restart services if needed

### If ESP32 Loses Connection

1. Check WiFi signal strength
2. Verify server IP hasn't changed
3. Check firewall settings
4. Restart ESP32
5. Re-upload code if needed

### If Database Issues

1. Check MySQL service status
2. Verify database credentials
3. Check disk space
4. Review error logs
5. Restore from backup if needed

## 📞 Support Contacts

- **System Administrator:** _________________
- **Network Administrator:** _________________
- **Hardware Technician:** _________________
- **Emergency Contact:** _________________

## 📝 Sign-Off

- [ ] System tested and verified by: _________________ Date: _______
- [ ] Documentation reviewed by: _________________ Date: _______
- [ ] Approved for production by: _________________ Date: _______

---

**Deployment Version:** 2.0  
**Deployment Date:** _________________  
**Next Review Date:** _________________
