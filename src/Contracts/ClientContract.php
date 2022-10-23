<?php

namespace Srustamov\Azericard\Contracts;

interface ClientContract
{
    public function getUrl() : string;

    public function setDebug(bool $debug): ClientContract;

    public function refund($params): bool|string;

    public function checkout($params): bool|string;
}
