<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Enums\ResponseCode;
use Srustamov\Azericard\Exceptions\ClientException;

class Client implements ClientContract
{
    public const TEST_URL = 'https://testmpi.3dsecure.az/cgi-bin/cgi_link';
    public const PRODUCTION_URL = 'https://mpi.3dsecure.az/cgi-bin/cgi_link';

    public const TEST_TOKEN_URL = 'https://testmpi.3dsecure.az/token/cgi_link';
    public const PRODUCTION_TOKEN_URL = 'https://mpi.3dsecure.az/token/cgi_link';

    /** @var array<string, string> */
    protected array $urls = [
        'test' => self::TEST_URL,
        'production' => self::PRODUCTION_URL,
    ];

    /** @var array<string, string> */
    protected array $tokenUrls = [
        'test' => self::TEST_TOKEN_URL,
        'production' => self::PRODUCTION_TOKEN_URL,
    ];

    protected bool $debug = false;

    protected ?string $response = null;

    public function __construct(
        protected int $timeout = 10,
        protected int $retryTimes = 1,
        protected int $retrySleep = 200,
        protected bool $verifySsl = true,
    ) {
    }

    public static function fake(string|ResponseCode $response = ResponseCode::Success): void
    {
        $body = $response instanceof ResponseCode ? $response->value : $response;

        $fake = Http::response($body, 200, ['Content-Type' => 'text/plain']);

        Http::fake([
            self::TEST_URL => $fake,
            self::PRODUCTION_URL => $fake,
            self::TEST_TOKEN_URL => $fake,
            self::PRODUCTION_TOKEN_URL => $fake,
        ]);
    }

    public function getUrl(): string
    {
        return $this->debug ? $this->urls['test'] : $this->urls['production'];
    }

    public function getTokenUrl(): string
    {
        return $this->debug ? $this->tokenUrls['test'] : $this->tokenUrls['production'];
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /** @param array<string, mixed> $params */
    public function createRefund(array $params): static
    {
        return $this->sendRequest($params);
    }

    /** @param array<string, mixed> $params */
    public function completeOrder(array $params): static
    {
        return $this->sendRequest($params);
    }

    /** @param array<string, mixed> $params */
    public function send(array $params, ?string $url = null): static
    {
        return $this->sendRequest($params, $url);
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function getResponseCode(): ?ResponseCode
    {
        return $this->response === null
            ? null
            : ResponseCode::tryFrom($this->response);
    }

    public function isApproved(): bool
    {
        return $this->response === ResponseCode::Success->value;
    }

    public function isDuplicate(): bool
    {
        return $this->response === ResponseCode::Duplicate->value;
    }

    /** @param array<string, mixed> $params */
    protected function sendRequest(array $params = [], ?string $url = null): static
    {
        $response = $this->request()->post($url ?? $this->getUrl(), $params);

        if (!$response->successful()) {
            $exception = $response->toException();

            throw new ClientException(
                $exception?->getMessage() ?? 'Azericard request failed with status ' . $response->status(),
                (int) ($exception?->getCode() ?: $response->status()),
                $exception,
            );
        }

        // Gateway returns a bare code in a text/plain body, often with trailing whitespace.
        $this->response = trim($response->body());

        return $this;
    }

    protected function request(): PendingRequest
    {
        $request = Http::asForm()
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleep, throw: false);

        return $this->verifySsl ? $request : $request->withoutVerifying();
    }

    public function __toString(): string
    {
        return $this->response ?? '';
    }
}
