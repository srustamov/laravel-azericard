<?php

namespace Srustamov\Azericard;

use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

class SignatureGenerator implements SignatureGeneratorContract
{

    public const ALGORITHM = 'sha1';

    public function __construct(protected string $sign = '') {}

    public function setSign(string $sign): static
    {
        $this->sign = $sign;

        return $this;
    }

    public function getPSign(Azericard $azericard): string
    {
        $string = strlen((string)$azericard->getAmount()) . $azericard->getAmount()
            . strlen((string)$azericard->options->get('currency')) . $azericard->options->get('currency')
            . strlen($azericard->getPaymentOrderId()) . $azericard->getPaymentOrderId();

        $keys = [
            'description',
            'merchant_name',
            'merchant_url',
            'terminal',
            'email',
            'tr_type',
            'country',
            'merchant_gmt',
            'timestamp',
            'nonce',
            'back_ref_url',
        ];

        foreach ($keys as $key) {
            $string .= strlen((string)$azericard->options->get($key)) . $azericard->options->get($key);
        }

        return $this->generateSign($string);
    }

    public function generateSign($data): string
    {
        $string = "";

        for ($i = 0, $iMax = strlen($this->sign); $i < $iMax; $i += 2) {
            $string .= chr(hexdec(substr($this->sign, $i, 2)));
        }

        return hash_hmac(static::ALGORITHM, $data, $string);
    }

    public function getSignForCheckout(Azericard $azericard, $request): string
    {
        $string = "" . strlen($request[Options::ORDER]) . $request[Options::ORDER] .
            strlen($request[Options::AMOUNT]) . $request[Options::AMOUNT] .
            strlen($request[Options::CURRENCY]) . $request[Options::CURRENCY] .
            strlen($request[Options::RRN]) . $request[Options::RRN] .
            strlen($request[Options::INT_REF]) . $request[Options::INT_REF] .
            strlen("21") . "21" .
            strlen($request[Options::TERMINAL]) . $request[Options::TERMINAL] .
            strlen($azericard->options->get('timestamp')) . $azericard->options->get('timestamp') .
            strlen($azericard->options->get('nonce')) . $azericard->options->get('nonce');

        return $this->generateSign($string);
    }

    public function generateForRefund(array $params): string
    {
        $keys = [
            Options::ORDER,
            Options::AMOUNT,
            Options::CURRENCY,
            Options::RRN,
            Options::INT_REF,
            Options::TRTYPE,
            Options::TERMINAL,
            Options::TIMESTAMP,
            Options::NONCE,
        ];

        $string = "";
        foreach ($keys as $key) {
            $string .= strlen($params[$key]) . $params[$key];
        }
        return $this->generateSign($string);
    }
}
