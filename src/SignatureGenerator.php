<?php

namespace Srustamov\Azericard;

use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

class SignatureGenerator implements SignatureGeneratorContract
{
    public function __construct(protected string $sign = '')
    {
    }

    public function setSign(string $sign): static
    {
        $this->sign = $sign;

        return $this;
    }

    public function getPSign(Azericard $azericard): string
    {
        $string = strlen((string)$azericard->getAmount()) . $azericard->getAmount()
            . strlen((string)$azericard->options->get('currency')) . $azericard->options->get('currency')
            . strlen($azericard->getPaymentOrderId()) . $azericard->getPaymentOrderId()
            . strlen((string)$azericard->options->get('description')) . $azericard->options->get('description')
            . strlen((string)$azericard->options->get('merchant_name')) . $azericard->options->get('merchant_name')
            . strlen((string)$azericard->options->get('merchant_url')) . $azericard->options->get('merchant_url')
            . strlen((string)$azericard->options->get('terminal')) . $azericard->options->get('terminal')
            . strlen((string)$azericard->options->get('email')) . $azericard->options->get('email')
            . strlen((string)$azericard->options->get('tr_type')) . $azericard->options->get('tr_type')
            . strlen((string)$azericard->options->get('country')) . $azericard->options->get('country')
            . strlen((string)$azericard->options->get('merchant_gmt')) . $azericard->options->get('merchant_gmt')
            . strlen((string)$azericard->options->get('timestamp')) . $azericard->options->get('timestamp')
            . strlen((string)$azericard->options->get('nonce')) . $azericard->options->get('nonce')
            . strlen((string)$azericard->options->get('back_ref_url')) . $azericard->options->get('back_ref_url');

        return $this->generateSign($string);
    }

    public function generateSign($data): string
    {
        $string = "";

        for ($i = 0, $iMax = strlen($this->sign); $i < $iMax; $i += 2) {
            $string .= chr(hexdec(substr($this->sign, $i, 2)));
        }

        return hash_hmac('sha1', $data, $string);
    }

    public function getSignForCheckout(Azericard $azericard, $request): string
    {
        $string = "" . strlen($request["ORDER"]) . $request["ORDER"] .
            strlen($request["AMOUNT"]) . $request["AMOUNT"] .
            strlen($request['CURRENCY']) . $request['CURRENCY'] .
            strlen($request["RRN"]) . $request["RRN"] .
            strlen($request["INT_REF"]) . $request["INT_REF"] .
            strlen("21") . "21" .
            strlen($request["TERMINAL"]) . $request["TERMINAL"] .
            strlen($azericard->options->get('timestamp')) . $azericard->options->get('timestamp') .
            strlen($azericard->options->get('nonce')) . $azericard->options->get('nonce');

        return $this->generateSign($string);
    }

    public function generateForRefund(array $params): string
    {
        $sign = strlen($params['ORDER']) . $params['ORDER']
            . strlen($params['AMOUNT']) . $params['AMOUNT']
            . strlen($params['CURRENCY']) . $params['CURRENCY']
            . strlen($params['RRN']) . $params['RRN']
            . strlen($params['INT_REF']) . $params['INT_REF']
            . strlen($params['TRTYPE']) . $params['TRTYPE']
            . strlen($params['TERMINAL']) . $params['TERMINAL']
            . strlen($params['TIMESTAMP']) . $params['TIMESTAMP']
            . strlen($params['NONCE']) . $params['NONCE'];


        return $this->generateSign($sign);
    }
}
