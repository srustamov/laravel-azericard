<?php

namespace Srustamov\Azericard\Contracts;

interface ClientContract
{
    public function getUrl(): string;

    public function setDebug(bool $debug): ClientContract;

    public function createRefund($params): bool|string;

    public function completeOrder($params): bool|string;
}
