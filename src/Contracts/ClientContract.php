<?php

namespace Srustamov\Azericard\Contracts;

use Stringable;

interface ClientContract extends Stringable
{
    public function getUrl(): string;

    public function setDebug(bool $debug): ClientContract;

    public function createRefund($params): ClientContract;

    public function completeOrder($params): ClientContract;

    public function isApproved(): bool;

    public function isDuplicate(): bool;

    public function getResponse(): ?string;
}
