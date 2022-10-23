<?php

namespace Srustamov\Azericard\Tests;


use Srustamov\Azericard\Facade\Azericard;

class CheckoutTest extends TestCase
{
    public function test_checkout()
    {
        $checkout = Azericard::checkout([
            "ACTION"   => "0",
            "ORDER"    => "123456",
            "AMOUNT"   => 100,
            "CURRENCY" => "944",
            "INT_REF"  => "Test",
            "RRN"      => "Test",
            "TERMINAL" => 23546576587,
        ]);

        $this->assertTrue($checkout);
    }
}
