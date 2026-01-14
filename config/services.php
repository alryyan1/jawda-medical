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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ultramsg' => [
        'base_url' => env('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com'),
        'instance_id' => env('ULTRAMSG_INSTANCE_ID'),
        'token' => env('ULTRAMSG_TOKEN'),
        'default_country_code' => env('ULTRAMSG_DEFAULT_COUNTRY_CODE', '249'), // Example for Sudan
    ],

    'realtime' => [
        'url' => env('REALTIME_SERVER_URL', 'http://localhost:3001'),
        'token' => env('REALTIME_SERVER_TOKEN'),
    ],

    'whatsapp_cloud' => [
        'token' => env('WHATSAPP_CLOUD_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_CLOUD_WABA_ID'),
        'api_version' => env('WHATSAPP_CLOUD_API_VERSION', 'v22.0'),
    ],

    'airtel_sms' => [
        'base_url' => env('AIRTEL_SMS_BASE_URL', 'https://www.airtel.sd'),
        'endpoint' => env('AIRTEL_SMS_ENDPOINT', '/api/rest_send_sms/'),
        'api_key' => env('AIRTEL_SMS_API_KEY'),
        'default_sender' => env('AIRTEL_SMS_SENDER', 'JAWDA'),
        'timeout' => env('AIRTEL_SMS_TIMEOUT', 10),
        // Optional credentials if needed in future
        'user_id' => env('AIRTEL_SMS_USER_ID'),
        'api_id' => env('AIRTEL_SMS_API_ID'),
        'user_identifier' => env('AIRTEL_SMS_USER_IDENTIFIER'),
        'password' => env('AIRTEL_SMS_PASSWORD'),
    ],

];
