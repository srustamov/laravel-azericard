<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Contracts;

use Srustamov\Azericard\Enums\ResponseCode;
use Stringable;

interface ClientContract extends Stringable
{
    public function getUrl(): string;

    public function getTokenUrl(): string;

    public function setDebug(bool $debug): static;

    /** @param array<string, mixed> $params */
    public function createRefund(array $params): static;

    /** @param array<string, mixed> $params */
    public function completeOrder(array $params): static;

    /** @param array<string, mixed> $params */
    public function send(array $params, ?string $url = null): static;

    public function isApproved(): bool;

    public function isDuplicate(): bool;

    public function getResponse(): ?string;

    public function getResponseCode(): ?ResponseCode;
}
