<?php

namespace Srustamov\Azericard\Contracts;

interface SignatureGeneratorContract
{
    public function verifySignature(string $data, string $signature): bool;

    public function getPSignForCreateOrder(array $params): string;

    public function generateSignKey($data): string;

    public function getPSignForCompleteOrder(array $params): string;

    public function generatePSignForRefund(array $params): string;

    public function hasPublicKey(): bool;

    public function generateSignContent(array $data, array $keys): string;
}
