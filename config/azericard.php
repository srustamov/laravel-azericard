<?php

declare(strict_types=1);

use Srustamov\Azericard\Enums\Language;
use Srustamov\Azericard\Options;
use Srustamov\Azericard\SignatureGenerator;

return [
    // true => test gateway (testmpi.3dsecure.az)
    Options::DEBUG => (bool) env('AZERICARD_DEBUG', false),

    Options::TERMINAL => env('AZERICARD_TERMINAL'),

    Options::MERCH_NAME => env('AZERICARD_MERCH_NAME', config('app.name')),
    Options::MERCH_URL => env('AZERICARD_MERCH_URL', config('app.url')),
    Options::MERCH_GMT => env('AZERICARD_MERCH_GMT', '+4'),
    Options::DESC => env('AZERICARD_DESC', 'Online payment'),
    Options::EMAIL => env('AZERICARD_EMAIL'),
    Options::COUNTRY => env('AZERICARD_COUNTRY', 'AZ'),
    Options::LANG => env('AZERICARD_LANG', Language::Az->value),
    Options::CURRENCY => env('AZERICARD_CURRENCY', 'AZN'),

    // callback url the gateway redirects the customer back to
    Options::BACKREF => env('AZERICARD_BACKREF'),

    Options::TIMEOUT => (int) env('AZERICARD_TIMEOUT', 10),
    Options::RETRY_TIMES => (int) env('AZERICARD_RETRY_TIMES', 1),
    Options::RETRY_SLEEP => (int) env('AZERICARD_RETRY_SLEEP', 200),

    // never disable outside local debugging: this is a payment endpoint
    Options::VERIFY_SSL => (bool) env('AZERICARD_VERIFY_SSL', true),

    'keys' => [
        // absolute path or inline PEM contents
        SignatureGenerator::PRIVATE_KEY_NAME => env('AZERICARD_PRIVATE_KEY'),

        // leave empty to SKIP callback signature verification (insecure)
        SignatureGenerator::PUBLIC_KEY_NAME => env('AZERICARD_PUBLIC_KEY'),
    ],
];
