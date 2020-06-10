<?php

return [
    //test mode
    'debug' => false,

    'urls' => [
        'test' => 'https://testmpi.3dsecure.az/cgi-bin/cgi_link',
        'production' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
        'backref' => 'https://example.com/azericard/return'
    ],

    'log_path' => storage_path('logs/azericard'),

    'TIMESTAMP' => gmdate('YmdHis'),
    'NONCE' => substr(md5(mt_rand()), 0, 16),
    'MERCH_NAME' => 'Merchnat name',
    'MERCH_URL' => 'https://example.com',
    'EMAIL' => 'info@example.com',
    'COUNTRY' => 'AZ',
    'MERCH_GMT' => '+4',
    'TERMINAL' => 17200000,
    'KEY_FOR_SIGN' => env('AZERICARD_KEY_FOR_SIGN'),
];
