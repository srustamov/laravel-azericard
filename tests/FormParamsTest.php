<?php

namespace Srustamov\Azericard\Tests;

use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Options;

class FormParamsTest extends TestCase
{
    public function test_form_params()
    {
        $azericard = Azericard::setAmount(100)->setOrder('123456')->setDebug(false);

        $params = $azericard->getFormParams();

        $this->assertEquals('123456', $params['inputs'][Options::ORDER]);
        $this->assertEquals(100, $params['inputs'][Options::AMOUNT]);
        $this->assertEquals($azericard->getClient()->getUrl(), $params['action']);
    }
}
