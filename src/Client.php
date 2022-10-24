<?php

namespace Srustamov\Azericard;

use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Exceptions\ClientException;

class Client implements ClientContract
{
    protected array $urls = [
        'test'       => 'https://testmpi.3dsecure.az/cgi-bin/cgi_link',
        'production' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
    ];

    protected bool $debug = false;

    protected static bool $fake;

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

    public function refund($params): bool|string
    {
        if (static::$fake) {
            return Azericard::SUCCESS;
        }

        $curl = curl_init($this->getUrl());

        curl_setopt_array($curl, [
            CURLOPT_POSTREDIR      => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $content = curl_exec($curl);

        if (curl_errno($curl)) {

            $error = curl_error($curl);

            curl_close($curl);

            throw new ClientException($error);
        }

        curl_close($curl);

        return $content;
    }


    public function checkout($params): bool|string
    {
        if (static::$fake) {
            return Azericard::SUCCESS;
        }

        $curl = curl_init($this->getUrl());

        curl_setopt_array($curl, [
            CURLOPT_POSTREDIR      => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $content = curl_exec($curl);

        if (curl_errno($curl)) {

            $error = curl_error($curl);

            curl_close($curl);

            throw new ClientException($error);
        }

        curl_close($curl);

        return $content;
    }

}
