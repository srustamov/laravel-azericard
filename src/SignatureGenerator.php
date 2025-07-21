<?php

namespace Srustamov\Azericard;


use Srustamov\Azericard\Exceptions\AzericardException;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

class SignatureGenerator implements SignatureGeneratorContract
{
    public const ALGORITHM = 'sha256WithRSAEncryption';

    public const PRIVATE_KEY_NAME = 'private';
    public const PUBLIC_KEY_NAME = 'public';

    public function __construct(public array $config = [])
    {
        $privateKeyFile = $this->config[static::PRIVATE_KEY_NAME] ?? null;

        if (!$privateKeyFile) {
            throw new AzericardException("Private key file path required");
        }

        if (!file_exists($privateKeyFile)) {
            throw new AzericardException("Private key file not found");
        }
    }

    public function hasPublicKey(): bool
    {
        return !empty($this->config[static::PUBLIC_KEY_NAME]);
    }

    public function verifySignature(string $data, string $signature): bool
    {
        if ($this->hasPublicKey()) {
            return (bool)openssl_verify(
                data: $data,
                signature: $signature,
                public_key: file_get_contents($this->config[static::PUBLIC_KEY_NAME]),
                algorithm: OPENSSL_ALGO_SHA256
            );
        }

        return true;
    }

    public function generateSignKey($data): string
    {
        openssl_sign(
            data: $data,
            signature: $signature,
            private_key: file_get_contents($this->config[static::PRIVATE_KEY_NAME]),
            algorithm: static::ALGORITHM
        );

        return bin2hex($signature);
    }

    public function getPSignForCreateOrder(array $params): string
    {
        return $this->generateSignKey(
            $this->generateSignContent($params, Options::CREATE_ORDER_SIGN_PARAMS)
        );
    }

    public function getPSignForCompleteOrder(array $params): string
    {
        return $this->generateSignKey(
            $this->generateSignContent($params, Options::COMPLETE_ORDER_SIGN_PARAMS)
        );
    }

    public function generatePSignForRefund(array $params): string
    {
        return $this->generateSignKey(
            $this->generateSignContent($params, Options::REFUND_ORDER_SIGN_PARAMS)
        );
    }

    public function generateSignContent(array $data, array $keys): string
    {
        $content = "";

        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            $value = $data[$key];

            $content .= strlen((string)$value) . $value;
        }

        return $content;
    }
}
