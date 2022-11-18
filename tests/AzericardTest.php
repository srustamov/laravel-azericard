<?php

namespace Srustamov\Azericard\Tests;

use Srustamov\Azericard\Client;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Options;
use Srustamov\Azericard\RefundData;

class AzericardTest extends TestCase
{


    public function test_form_params()
    {
        $azericard = Azericard::setAmount(100)->setOrder('000001')->setDebug(false);

        $params = $azericard->createOrder();

        $this->assertEquals('000001', $params['inputs'][Options::ORDER]);
        $this->assertEquals(100, $params['inputs'][Options::AMOUNT]);
        $this->assertEquals($azericard->getClient()->getUrl(), $params['action']);
    }

    public function test_complete()
    {
        Client::fake();

        $data = [
            Options::ACTION   => Options::RESPONSE_CODES['SUCCESS'],
            Options::ORDER    => "123456",
            Options::AMOUNT   => 100,
            Options::CURRENCY => "AZN",
            Options::TRTYPE   => "0",
            Options::INT_REF  => "Test",
            Options::RRN      => "Test",
            Options::TERMINAL => 23546576587,
        ];

        /**@var $signatureGenerator SignatureGeneratorContract */
        $signatureGenerator = app(SignatureGeneratorContract::class);

        $signature = $signatureGenerator->generateSignKey(
            $signatureGenerator->generateSignContent(
                $data,
                Options::COMPLETE_ORDER_SIGN_PARAMS
            )
        );

        $data[Options::P_SIGN] = $signature;

        $complete = Azericard::completeOrder($data);

        $this->assertTrue($complete);
    }

    public function test_refund()
    {
        Client::fake();

        $data = new RefundData(
            rrn: "465854346234784",
            int_ref: "4u9078u4",
            created_at: now()
        );

        $refund = Azericard::setOrder("000002")->setAmount(100)->refund($data);

        $this->assertTrue($refund);
    }
}
