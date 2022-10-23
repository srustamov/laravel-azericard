<?php

namespace Srustamov\Azericard\Contracts;

use Srustamov\Azericard\Azericard;

interface SignatureGeneratorContract
{
    public function setSign(string $sign): static;

    public function getPSign(Azericard $azericard): string;

    public function getSignForCheckout(Azericard $azericard,$request): string;

    public function generateForRefund(array $params): string;
}