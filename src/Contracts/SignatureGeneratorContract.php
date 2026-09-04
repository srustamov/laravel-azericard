<?php

declare(strict_types=1);

namespace Srustamov\Azericard\Contracts;

interface SignatureGeneratorContract
{
    public function verifySignature(string $data, string $signature): bool;

    public function generateSignKey(string $data): string;

    /** @param array<string, mixed> $params */
    public function getPSignForCreateOrder(array $params): string;

    /** @param array<string, mixed> $params */
    public function getPSignForCompleteOrder(array $params): string;

    /** @param array<string, mixed> $params */
    public function generatePSignForRefund(array $params): string;

    public function hasPublicKey(): bool;

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    public function generateSignContent(array $data, array $keys): string;
}
