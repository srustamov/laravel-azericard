<?php namespace Srustamov\Azericard;


interface PaymentGatewayInterface
{

    /**
     * @return string
     */
    public function getPaymentOrderId();

    /**
     * @return double
     */
    public function getPaymentAmount();

    /**
     * @return string
     */
    public function getPaymentDescription();

    /**
     * @return string
     */
    public function getCustomerEmail();


}
