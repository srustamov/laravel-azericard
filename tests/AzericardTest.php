<?php

namespace Srustamov\Azericard\Tests;

use Srustamov\Azericard\Options;
use Illuminate\Support\Facades\Event;
use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Events\OrderCreated;
use Srustamov\Azericard\Events\OrderCreating;
use Srustamov\Azericard\Events\OrderRefunded;
use Srustamov\Azericard\Events\OrderCompleted;
use Srustamov\Azericard\DataProviders\RefundData;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

class AzericardTest extends TestCase
{
    public function test_form_params()
    {
        $azericard = Azericard::setAmount(100)->setOrder('000001')->setDebug(false);

        Event::listen(OrderCreating::class, function (OrderCreating $event) {
            $this->assertEquals('000001', $event->orderId);
            $this->assertEquals(100, $event->amount);
        });

        Event::listen(OrderCreated::class, function (OrderCreated $event) {
            $this->assertEquals(100, $event->data['inputs'][Options::AMOUNT]);
        });

        $params = $azericard->createOrder();

        $this->assertEquals('000001', $params['inputs'][Options::ORDER]);
        $this->assertEquals(100, $params['inputs'][Options::AMOUNT]);
        $this->assertEquals($azericard->getClient()->getUrl(), $params['action']);
    }

    public function test_complete()
    {
        app(ClientContract::class)->fake();

        Event::listen(OrderCompleted::class, function (OrderCompleted $event) {
            $this->assertEquals($event->response, Options::RESPONSE_CODES['SUCCESS']);
        });

        $data = [
            Options::ACTION => Options::RESPONSE_CODES['SUCCESS'],
            Options::ORDER => "123456",
            Options::AMOUNT => 100,
            Options::CURRENCY => "AZN",
            Options::TRTYPE => "0",
            Options::INT_REF => "Test",
            Options::RRN => "Test",
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
        app(ClientContract::class)->fake();

        Event::listen(OrderRefunded::class, function (OrderRefunded $event) {
            $this->assertEquals("000002", $event->data[Options::ORDER]);
        });

        $data = new RefundData(
            rrn: "465854346234784",
            int_ref: "4u9078u4",
            created_at: now()
        );

        $refund = Azericard::setOrder("000002")->setAmount(100)->refund($data);

        $this->assertTrue($refund);
    }
}
