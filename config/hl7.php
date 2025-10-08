<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HL7 Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the HL7 TCP server that receives messages from
    | laboratory devices
    |
    */
    'server' => [
        'host' => env('HL7_SERVER_HOST', '127.0.0.1'),
        'port' => env('HL7_SERVER_PORT', 6400),
        'enabled' => env('HL7_SERVER_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HL7 Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the HL7 TCP client that connects to laboratory devices
    | to receive messages
    |
    */
    'client' => [
        'host' => env('HL7_CLIENT_HOST', '192.168.1.114'),
        'port' => env('HL7_CLIENT_PORT', 5100),
        'reconnect_delay' => env('HL7_CLIENT_RECONNECT_DELAY', 5),
        'enabled' => env('HL7_CLIENT_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HL7 Message Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for HL7 message processing and logging
    |
    */
    'processing' => [
        'log_raw_messages' => env('HL7_LOG_RAW_MESSAGES', true),
        'log_processed_messages' => env('HL7_LOG_PROCESSED_MESSAGES', true),
        'max_message_size' => env('HL7_MAX_MESSAGE_SIZE', 65536), // 64KB
        'timeout' => env('HL7_TIMEOUT', 30), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Devices
    |--------------------------------------------------------------------------
    |
    | List of supported laboratory devices and their configurations
    |
    */
    'devices' => [
        'MaglumiX3' => [
            'enabled' => true,
            'description' => 'Maglumi X3 Immunoassay Analyzer',
        ],
        'CL-900' => [
            'enabled' => true,
            'description' => 'Mindray CL-900 Chemistry Analyzer',
        ],
        'BC-6800' => [
            'enabled' => true,
            'description' => 'Mindray BC-6800 Hematology Analyzer',
        ],
        'ACON' => [
            'enabled' => true,
            'description' => 'ACON Laboratory Information System',
        ],
        'Z3' => [
            'enabled' => true,
            'description' => 'Zybio Z3 Analyzer',
        ],
        'URIT' => [
            'enabled' => true,
            'description' => 'URIT Medical Electronic Analyzer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HL7 message storage and logging
    |
    */
    'database' => [
        'log_messages' => env('HL7_DB_LOG_MESSAGES', true),
        'table_name' => env('HL7_DB_TABLE', 'hl7_messages'),
        'retention_days' => env('HL7_DB_RETENTION_DAYS', 30),
    ],
];