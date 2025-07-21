<?php

use Srustamov\Azericard\Options;
use Srustamov\Azericard\SignatureGenerator;

return [
    //test mode
    Options::DEBUG    => false,

    // Your bank terminal number
    Options::TERMINAL => 17200000,

    Options::MERCH_NAME => 'your_merchant_name',
    Options::MERCH_GMT  => '+4',
    Options::DESC       => 'Your company description',
    Options::EMAIL      => 'payment@example.az',
    Options::COUNTRY    => 'AZ',
    Options::LANG       => 'AZ',

    Options::CURRENCY => 'AZN',

    //your callback url
    Options::BACKREF  => '',

    'keys' => [
        //it is required,
        //for example, storage_path('secrets/private.pem')
        SignatureGenerator::PRIVATE_KEY_NAME => 'private_key_file_path',

        //if you do not want to verify signature then set null
        SignatureGenerator::PUBLIC_KEY_NAME  => null,
    ],
];
