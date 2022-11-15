<?php

namespace Srustamov\Azericard;

use Illuminate\Support\Facades\Http;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Exceptions\ClientException;

class Client implements ClientContract
{
    protected array $urls = [
        'test'       => 'https://testmpi.3dsecure.az/cgi-bin/cgi_link',
        'production' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
    ];

    protected bool $debug = false;

    protected static bool $fake = false;

    public static function fake()
    {
        static::$fake = true;
    }

    public function getUrl() : string
    {
        return $this->debug ? $this->urls['test'] : $this->urls['production'];
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    public function createRefund($params): bool|string
    {
        return $this->sendRequest($params);
    }


    public function completeOrder($params): string
    {
        return $this->sendRequest($params);
    }

    protected function sendRequest(array $params = []): string
    {
        if (static::$fake) {
            return Options::RESPONSE_CODES["SUCCESS"];
        }

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->asForm()
            ->post($this->getUrl(),$params);

        if ($response->successful()) {
            return $response->body();
        }

        throw new ClientException(
            $response->toException()->getMessage(),
            $response->toException()->getCode(),
        );
    }

}
