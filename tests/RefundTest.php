<?php

namespace Srustamov\Azericard\Tests;

use Srustamov\Azericard\Client;
use Srustamov\Azericard\Facade\Azericard;


class RefundTest extends TestCase
{

    public function test_refund()
    {
        Client::fake();

        $refund = Azericard::setOrder("1")->setAmount(100)->refund([
            'rrn'      => "465854346234784",
            'int_ref'  => "...",
            'created_at' => now(),
        ]);

        $this->assertTrue($refund);
    }
}
