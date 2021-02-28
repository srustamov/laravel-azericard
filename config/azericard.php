<?php

return [
    //test mode
    'debug' => false,

    // Azericard sign key
    'sign' => '',

    // Your bank terminal number
    'terminal' => 17200000,

    'merchant_name' => 'Your Company Name',
    'merchant_gmt' => '+4',
    'description' => 'Your company description',
    'email' => 'payment@example.az',
    'country' => 'AZ',
    'lang' => 'AZ',
    'log_path' => storage_path('logs/azericard'),

    'urls' => [
        'test' => 'https://testmpi.3dsecure.az/cgi-bin/cgi_link',
        'production' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
        
        // Azericard payment gateway callback url
        // Example : test.com/azericard/callback =>  this is POST route
        // Example route: Route::post('/azericard/callback','AzericardController@callback')

        'return' => 'callback url'
    ]
];
