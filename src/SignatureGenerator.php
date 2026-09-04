<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use SensitiveParameter;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;
use Srustamov\Azericard\Exceptions\AzericardException;

class SignatureGenerator implements SignatureGeneratorContract
{
    public const ALGORITHM = OPENSSL_ALGO_SHA256;

    public const PRIVATE_KEY_NAME = 'private';
    public const PUBLIC_KEY_NAME = 'public';

    private const PEM_PREFIX = '-----BEGIN';

    /** @var array<string, string> */
    private array $resolved = [];

    /** @param array<string, string|null> $config */
    public function __construct(
        #[SensitiveParameter]
        public array $config = []
    ) {
    }

    public function hasPublicKey(): bool
    {
        return filled($this->config[self::PUBLIC_KEY_NAME] ?? null);
    }

    public function hasPrivateKey(): bool
    {
        return filled($this->config[self::PRIVATE_KEY_NAME] ?? null);
    }

    // No public key configured => verification is SKIPPED. Set azericard.keys.public in production.
    public function verifySignature(string $data, string $signature): bool
    {
        if (!$this->hasPublicKey()) {
            return true;
        }

        $binarySignature = $this->normalizeSignature($signature);

        if ($binarySignature === null) {
            return false;
        }

        // openssl_verify() returns 1 (valid), 0 (invalid) or -1 (error).
        return openssl_verify(
            data: $data,
            signature: $binarySignature,
            public_key: $this->key(self::PUBLIC_KEY_NAME),
            algorithm: self::ALGORITHM
        ) === 1;
    }

    public function generateSignKey(string $data): string
    {
        $signed = openssl_sign(
            data: $data,
            signature: $signature,
            private_key: $this->key(self::PRIVATE_KEY_NAME),
            algorithm: self::ALGORITHM
        );

        if (!$signed) {
            throw new AzericardException('Unable to sign the request: ' . (openssl_error_string() ?: 'unknown openssl error'));
        }

        return bin2hex($signature);
    }

    /** @param array<string, mixed> $params */
    public function getPSignForCreateOrder(array $params): string
    {
        return $this->generateSignKey(
            $this->generateSignContent($params, Options::CREATE_ORDER_SIGN_PARAMS)
        );
    }

    /** @param array<string, mixed> $params */
    public function getPSignForCompleteOrder(array $params): string
    {
        return $this->generateSignKey(
            $this->generateSignContent($params, Options::COMPLETE_ORDER_SIGN_PARAMS)
        );
    }

    /** @param array<string, mixed> $params */
    public function generatePSignForRefund(array $params): string
    {
        return $this->generateSignKey(
            $this->generateSignContent($params, Options::REFUND_ORDER_SIGN_PARAMS)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    public function generateSignContent(array $data, array $keys): string
    {
        $content = '';

        foreach ($keys as $key) {
            $value = isset($data[$key]) ? (string) $data[$key] : '';

            // Spec: a missing value is replaced by "-" and its length is NOT prefixed.
            $content .= $value === '' ? '-' : strlen($value) . $value;
        }

        return $content;
    }

    // Gateway sends P_SIGN hex-encoded; openssl_verify needs the raw binary.
    private function normalizeSignature(string $signature): ?string
    {
        $signature = trim($signature);

        if ($signature === '') {
            return null;
        }

        if (strlen($signature) % 2 === 0 && ctype_xdigit($signature)) {
            $binary = @hex2bin($signature);

            if ($binary !== false) {
                return $binary;
            }
        }

        return $signature;
    }

    private function key(string $name): string
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $value = $this->config[$name] ?? null;

        if (!filled($value)) {
            throw new AzericardException(sprintf('Azericard %s key is not configured.', $name));
        }

        $value = (string) $value;

        if (str_contains($value, self::PEM_PREFIX)) {
            return $this->resolved[$name] = $value;
        }

        if (!is_file($value) || !is_readable($value)) {
            throw new AzericardException(sprintf('Azericard %s key file not found or not readable: %s', $name, $value));
        }

        $contents = file_get_contents($value);

        if ($contents === false) {
            throw new AzericardException(sprintf('Unable to read the Azericard %s key file: %s', $name, $value));
        }

        return $this->resolved[$name] = $contents;
    }
}
