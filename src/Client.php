<?php

namespace Srustamov\Azericard;

use Illuminate\Support\Facades\Http;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Exceptions\ClientException;

class Client implements ClientContract
{
    protected array $urls = [
        'test' => 'https://testmpi.3dsecure.az/cgi-bin/cgi_link',
        'production' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
    ];

    protected bool $debug = false;

    protected ?string $response = null;

    public static function fake(): void
    {
        Http::fake([
            'https://testmpi.3dsecure.az/cgi-bin/cgi_link' => Http::response(
                Options::RESPONSE_CODES['SUCCESS'],
                200,
                ['Content-Type' => 'text/plain']
            ),
            'https://mpi.3dsecure.az/cgi-bin/cgi_link' => Http::response(
                Options::RESPONSE_CODES['SUCCESS'],
                200,
                ['Content-Type' => 'text/plain']
            ),
        ]);
    }

    public function getUrl(): string
    {
        return $this->debug ? $this->urls['test'] : $this->urls['production'];
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;

        return $this;
    }

    public function createRefund($params): static
    {
        return $this->sendRequest($params);
    }


    public function completeOrder($params): static
    {
        return $this->sendRequest($params);
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function isApproved(): bool
    {
        return $this->response === Options::RESPONSE_CODES['SUCCESS'];
    }

    public function isDuplicate(): bool
    {
        return $this->response === Options::RESPONSE_CODES['DUPLICATE'];
    }

    protected function sendRequest(array $params = []): static
    {
        $response = Http::withoutVerifying()
            ->timeout(10)
            ->asForm()
            ->post($this->getUrl(), $params);

        if ($response->successful()) {

            $this->response = $response->body();

            return $this;
        }

        throw new ClientException(
            $response->toException()->getMessage(),
            $response->toException()->getCode(),
        );
    }

    public function __toString(): string
    {
        return $this->response ?? '';
    }
}
