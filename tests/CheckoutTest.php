<?php

namespace Srustamov\Azericard\Tests;


use Srustamov\Azericard\Client;
use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Options;

class CheckoutTest extends TestCase
{
    public function test_checkout()
    {
        Client::fake();

        $checkout = Azericard::checkout([
            Options::ACTION   => \Srustamov\Azericard\Azericard::SUCCESS,
            Options::ORDER    => "123456",
            Options::AMOUNT   => 100,
            Options::CURRENCY => "AZN",
            Options::INT_REF  => "Test",
            Options::RRN      => "Test",
            Options::TERMINAL => 23546576587,
        ]);

        $this->assertTrue($checkout);
    }
}
