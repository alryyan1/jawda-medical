<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HL7 Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the HL7 TCP server that receives messages from
    | laboratory devices like Maglumi X3, Mindray CL-900, and BC-6800.
    |
    */

    'server' => [
        'host' => env('HL7_SERVER_HOST', '127.0.0.1'),
        'port' => env('HL7_SERVER_PORT', 6400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Mappings
    |--------------------------------------------------------------------------
    |
    | Maps device identifiers to their corresponding handler classes.
    |
    */

    'devices' => [
        'MaglumiX3' => \App\Services\HL7\Devices\MaglumiX3Handler::class,
        'CL-900' => \App\Services\HL7\Devices\MindrayCL900Handler::class,
        'BC-6800' => \App\Services\HL7\Devices\BC6800Handler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Mappings
    |--------------------------------------------------------------------------
    |
    | Maps main test IDs to device-specific test codes.
    |
    */

    'test_mappings' => [
        'hormone' => [
            170 => "TSH",
            171 => "T3",
            178 => "T4",
            271 => "C-P",
            365 => "TSH",
            232 => "T3",
            233 => "T4",
            191 => "FSH",
            234 => "FSH",
            237 => "PRL",
            76 => "PSA",
            192 => "LH",
            190 => "PRL",
            236 => "LH",
            77 => "ANA",
            90 => "25-OH VD II",
            175 => "FT3",
            229 => "FT3",
            228 => "FT3",
            362 => "FT4",
            226 => "FT4",
            105 => "E2",
            88 => "HCG/B-HCG",
            59 => "Troponin I",
        ],
        'chemistry' => [
            8 => 'xoxglug',
            10 => 'xoxglug',
            11 => 'xoxglug',
            70 => 'xoxna',
            71 => 'xoxk',
            12 => 'rft',
            14 => 'lipid',
            13 => 'liver',
            24 => 'xoxaso',
            220 => 'xoxaso',
            31 => 'xoxurea',
            231 => 'xoxck',
            240 => 'xoxckmb',
            82 => 'xoxfe',
            32 => 'xoxcrea',
            33 => 'xoxuric',
            35 => 'xoxca',
            156 => 'xoxmg',
            124 => 'xoxldh',
            189 => 'xoxlip',
            188 => 'xoxamyl',
            36 => 'xoxpho',
            47 => 'xoxhdl',
            49 => 'xoxldl',
            162 => 'xoxtp',
            163 => 'xoxalb',
            164 => 'xoxtb',
            165 => 'xoxdb',
            166 => 'xoxalp',
            168 => 'xoxast',
            169 => 'xoxalt',
            25 => 'xoxcrp',
            179 => 'xoxtc',
            180 => 'xoxtg',
            170 => "hoxtsh",
            365 => "hoxtsh",
            106 => "hoxafp",
            108 => "hoxcea",
            91 => "hoxca125",
            92 => "hoxca153",
            89 => "hoxca199",
            76 => "hoxpsa",
            98 => "hoxfpsa",
            113 => "hoxamh",
            230 => "hoxtesto",
            102 => "hoxprog",
            100 => "hoxcortiso",
            43 => "hoxhiv",
            41 => "hoxhbsag",
            339 => "hoxantihcv",
            85 => "hoxvb12",
            107 => "hoxpth",
            232 => "hoxt3",
            233 => "hoxt4",
            234 => "hoxfsh",
            237 => "hoxprl",
            236 => "hoxlh",
            90 => "hoxvitd",
            228 => "hoxFT3",
            229 => "hoxFT3",
            226 => "hoxFT4",
            105 => "hoxE2",
            88 => "hoxbhcg",
            59 => "hoxtrop",
        ],
    ],
];
