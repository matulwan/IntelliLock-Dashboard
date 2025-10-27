# Intelli-Lock Dashboard

A Complete IoT Key Management System with Real-Time Monitoring

## Overview

Intelli-Lock is a comprehensive IoT-based key management system that combines hardware (ESP32, RFID, fingerprint sensors) with a modern web dashboard built on Laravel and React.

## Key Features

- RFID & Fingerprint Authentication
- Photo Capture with ESP32-CAM
- Real-Time Alerts
- Analytics Dashboard
- Key Tracking
- RESTful API
- Responsive UI

## Quick Start

See QUICK_START.md for rapid deployment in 15 minutes.

## Documentation

- QUICK_START.md - Get running in 15 minutes
- SETUP_GUIDE.md - Complete installation guide
- ESP32_INTEGRATION_SETUP.md - Hardware setup
- test_iot_api.php - API testing script

## Technology Stack

### Backend
- Laravel 11.x
- MySQL 8.0+
- Laravel Sanctum

### Frontend
- React 18.x
- Inertia.js
- TailwindCSS
- shadcn/ui

### IoT Hardware
- ESP32
- MFRC522 RFID reader
- AS608/R307 Fingerprint sensor
- ESP32-CAM

## Installation

```bash
composer install && npm install
copy .env.production.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
npm run dev
```

## Database Tables

- users - System users with IoT credentials
- lab_keys - Physical keys with RFID tags
- key_transactions - Checkout/checkin history
- access_logs - All access attempts
- event_photos - Photos from ESP32-CAM
- system_alerts - Security alerts

## API Endpoints

- POST /api/iot/authenticate - User authentication
- POST /api/iot/key-transaction - Key checkout/checkin
- POST /api/iot/upload-photo - Photo upload
- POST /api/iot/alert - System alerts
- GET /api/iot/status - System status

## Testing

```bash
php test_iot_api.php
php artisan test
```

## License

MIT License

## Version

2.0 - Production Ready
