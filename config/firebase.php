<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase services including Storage and Authentication
    |
    */

    // Should match the service account JSON (project_id: "sales-9e9b8")
    'project_id' => env('FIREBASE_PROJECT_ID', 'sales-9e9b8'),
    // Use the default bucket for this project unless overridden in .env
    // Newer Firebase projects use .firebasestorage.app; legacy use .appspot.com
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'sales-9e9b8.firebasestorage.app'),
    'api_key' => env('FIREBASE_API_KEY'),

    'hospital' => [
        'project_id' => env('HOSPITAL_FIREBASE_PROJECT_ID', 'hospitalapp-681f1'),
        'api_key' => env('HOSPITAL_FIREBASE_API_KEY'),
        'service_account_path' => env('HOSPITAL_FIREBASE_SERVICE_ACCOUNT_PATH', storage_path('app/hospital-firebase-service-account.json')),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Firebase Service Account
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase service account JSON file
    |
    */
    'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', storage_path('app/firebase-service-account.json')),
    
    /*
    |--------------------------------------------------------------------------
    | Firebase Storage
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Storage uploads
    |
    */
    'storage' => [
        'default_path' => 'results',
        'hospital_name' => env('FIREBASE_HOSPITAL_NAME', 'Jawda Medical'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Firebase Authentication
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Authentication
    |
    */
    'auth' => [
        'access_token' => env('FIREBASE_ACCESS_TOKEN'),
        'refresh_token' => env('FIREBASE_REFRESH_TOKEN'),
    ],
];
