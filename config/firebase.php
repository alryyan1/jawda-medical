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

    'project_id' => env('FIREBASE_PROJECT_ID', 'hospitalapp-681f1'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'hospitalapp-681f1.firebasestorage.app'),
    
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
