<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // MQTT broker settings for IoT
    'mqtt' => [
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => env('MQTT_PORT', 1883),
        'username' => env('MQTT_USERNAME', ''),
        'password' => env('MQTT_PASSWORD', ''),
        'client_id' => env('MQTT_CLIENT_ID', 'intellilock-dashboard'),
        'topic_prefix' => env('MQTT_TOPIC_PREFIX', 'intellilock'),
        // TLS options for secure MQTT on port 8883
        'tls' => env('MQTT_TLS', false),
        'tls_verify_peer' => env('MQTT_TLS_VERIFY_PEER', false),
        'tls_allow_self_signed' => env('MQTT_TLS_ALLOW_SELF_SIGNED', true),
        'tls_ca_file' => env('MQTT_TLS_CA_FILE', null),
        'tls_ca_path' => env('MQTT_TLS_CA_PATH', null),
    ],

];
