<?php

namespace Srustamov\Azericard;


use Exception;
use Srustamov\Azericard\Exceptions\AzericardException;


class Azericard implements PaymentGatewayInterface
{
    use AzericardLogger;

    public $debug;

    public $sign;

    public $merchant_name;

    public $merchant_url;

    public $merchant_gmt;

    public $tr_type = 0;

    public $currency;

    public $amount;

    public $terminal;

    public $psign;

    public $timestamp;

    public $nonce;

    public $country;

    public $lang;

    public $irKey = 'INTREF';

    public $description;

    public $email;

    public $urls = [
        'test' => 'https://testmpi.3dsecure.az/cgi-bin/cgi_link',
        'production' => 'https://mpi.3dsecure.az/cgi-bin/cgi_link',
        'return' => ''
    ];

    public $order;

    protected $appends = [];



    public function __construct($currency = 'AZN')
    {
        $config = config('azericard');

        $this->sign           = $config['sign'];
        $this->urls           = $config['urls'];
        $this->urls['return'] = $config['urls']['return'];
        $this->debug          = $config['debug'];
        $this->irKey          = $this->debug ? 'INT_REF' : 'INTREF';
        $this->email          = $config['email'];
        $this->terminal       = $config['terminal'];
        $this->description    = $config['description'];
        $this->merchant_gmt   = $config['merchant_gmt'];
        $this->merchant_name  = $config['merchant_name'];
        $this->setLogPath($config['log_path']);

        $this->country   = $config['country'];
        $this->lang      = $config['lang'];
        $this->timestamp = gmdate("YmdHis");
        $this->currency  = $currency;
        $this->nonce     = substr(md5(mt_rand()), 0, 16);

    }




    /**
     * @param string $currency
     * @return Azericard
     */
    public static function newInstance($currency = 'AZN') : Azericard
    {
        return new self($currency);
    }


    /**
     * @param bool $boolean
     * @return $this
     */
    public function debug(bool $boolean)
    {
        $this->debug = $boolean;

        return $this;
    }


    /**
     * @param int|float $amount
     * @return $this
     */
    public function amount($amount): Azericard
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @param $order
     * @return $this
     */
    public function order($order): Azericard
    {
        $this->order = str_pad($order,6,'0',STR_PAD_LEFT);;

        return $this;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setMerchantUrl(string $url): Azericard
    {
        $this->merchant_url = $url;

        return $this;
    }


    /**
     * @param array $data
     * @return $this
     */
    public function appendFormParams(array $data): Azericard
    {
        $this->appends = array_merge($this->appends,$data);

        return $this;
    }


    /**
     * @return array
     */
    public function getFormParams(): array
    {
        if (!$this->amount || !$this->order) {
            throw new AzericardException('Payment required amount and order');
        }

        $this->generatePSign();

        return [
            "action" => $this->debug ? $this->urls['test'] : $this->urls['production'],
            'method' => 'POST',
            "inputs" => [
                "AMOUNT" => $this->amount,
                "CURRENCY" => $this->currency,
                "ORDER" => $this->order,
                "DESC" => $this->description,
                "MERCH_NAME" => $this->merchant_name,
                "MERCH_URL" => $this->merchant_url,
                "TERMINAL" => $this->terminal,
                "EMAIL" => $this->email,
                "TRTYPE" => $this->tr_type,
                "COUNTRY" => $this->country,
                "MERCH_GMT" => $this->merchant_gmt,
                "TIMESTAMP" => $this->timestamp,
                "NONCE" => $this->nonce,
                "BACKREF" => $this->urls['return'],
                "LANG" => $this->lang,
                "P_SIGN" => $this->psign,
            ]
        ] + $this->appends;
    }



    /**
     * @param array $callbackParameters
     * @return bool
     */
    public function refund(array $callbackParameters): bool
    {
        $requiredKeys = ['rrn','int_ref','created_at'];

        foreach($requiredKeys as $key) {
            abort_unless(
                isset($callbackParameters[$key]),
                400,
                '"rrn","int_ref","created_at" values ​​are required in the sent array.'
            );
        }

        try {

            $params['AMOUNT']    = round(number_format($this->amount, 2));
            $params['CURRENCY']  = 'AZN';
            $params['ORDER']     = $this->order;
            $params['RRN']       = $callbackParameters['rrn'];
            $params['INT_REF']   = $callbackParameters['int_ref'];
            $params['TERMINAL']  = $this->terminal;
            $params['TRTYPE']    = '22';
            $params['TIMESTAMP'] = $this->timestamp;
            $params['NONCE']     = $this->nonce;

            if($callbackParameters['created_at'] < date('Y-m-d H:i:s', strtotime('-1 day'))) {
                $params['TRTYPE'] = '24';
            }

            $sign = strlen($params['ORDER'])   . $params['ORDER']
                . strlen($params['AMOUNT'])    . $params['AMOUNT']
                . strlen($params['CURRENCY'])  . $params['CURRENCY']
                . strlen($params['RRN'])       . $params['RRN']
                . strlen($params['INT_REF'])   . $params['INT_REF']
                . strlen($params['TRTYPE'])    . $params['TRTYPE']
                . strlen($params['TERMINAL'])  . $params['TERMINAL']
                . strlen($params['TIMESTAMP']) . $params['TIMESTAMP']
                . strlen($params['NONCE'])     . $params['NONCE'];


            $params['P_SIGN'] = $this->generateSign($sign);

            $curl = curl_init($this->debug ? $this->urls['test'] : $this->urls['production']);


            curl_setopt_array($curl, [
                CURLOPT_POSTREDIR => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_AUTOREFERER => true,
                CURLOPT_CONNECTTIMEOUT => 120,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $content = curl_exec($curl);

            return $content === '0';

        } catch (Exception $exception) {

            $this->writeDebugLog('Refund-'.$exception->getMessage());

            return false;
        }
    }


    /**
     * @param $request
     * @return bool|null
     */
    public function checkout($request)
    {
        if ($request['ACTION'] != '0') {
            return false;
        }

        $request['ORDER'] = str_pad($request['ORDER'], 6, "0", STR_PAD_LEFT);

        $params = [];

        $params["ORDER"] = $request['ORDER'];
        $params["AMOUNT"] = $request["AMOUNT"];
        $params["CURRENCY"] = $request['CURRENCY'];
        $params["RRN"] = $request["RRN"];
        $params["INT_REF"] = $request["INT_REF"];
        $params["TERMINAL"] = $request["TERMINAL"];
        $params["TRTYPE"] = "21";
        $params["TIMESTAMP"] = $this->timestamp;
        $params["NONCE"] = $this->nonce;
        $params['P_SIGN'] = $this->generateSignForCheckout($request);


        try {
            $curl = curl_init($this->debug ? $this->urls['test'] : $this->urls['production']);

            curl_setopt_array($curl, [
                CURLOPT_POSTREDIR => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_AUTOREFERER => true,
                CURLOPT_CONNECTTIMEOUT => 120,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $content = curl_exec($curl);

            curl_close($curl);

            return $content === '0';

        } catch (Exception $exception) {

            $this->writeDebugLog($exception->getMessage(), [
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ]);

            return false;
        }

    }


    /**
     * @return $this
     */
    protected function generatePSign(): Azericard
    {
        $pSign =
            strlen((string)$this->amount) . $this->amount
            . strlen((string)$this->currency) . $this->currency
            . strlen((string)$this->order) . $this->order
            . strlen((string)$this->description) . $this->description
            . strlen((string)$this->merchant_name) . $this->merchant_name
            . strlen((string)$this->merchant_url) . $this->merchant_url
            . strlen((string)$this->terminal) . $this->terminal
            . strlen((string)$this->email) . $this->email
            . strlen((string)$this->tr_type) . $this->tr_type
            . strlen((string)$this->country) . $this->country
            . strlen((string)$this->merchant_gmt) . $this->merchant_gmt
            . strlen((string)$this->timestamp) . $this->timestamp
            . strlen((string)$this->nonce) . $this->nonce
            . strlen((string)$this->urls['return']) . $this->urls['return'];

        $this->psign = $this->generateSign($pSign);

        return $this;
    }


    protected function generateSignForCheckout($request): string
    {
        $string = "" . strlen($request["ORDER"]) . $request["ORDER"] .
            strlen($request["AMOUNT"]) . $request["AMOUNT"] .
            strlen($request['CURRENCY']) . $request['CURRENCY'] .
            strlen($request["RRN"]) . $request["RRN"] .
            strlen($request["INT_REF"]) . $request["INT_REF"] .
            strlen("21") . "21" .
            strlen($request["TERMINAL"]) . $request["TERMINAL"] .
            strlen($this->timestamp) . $this->timestamp .
            strlen($this->nonce) . $this->nonce;

        return $this->generateSign($string);
    }


    /**
     * @param $data
     * @return string
     */
    protected function generateSign($data): string
    {
        $string = "";

        for ($i = 0; $i < strlen($this->sign); $i += 2) {
            $string .= chr(hexdec(substr($this->sign, $i, 2)));
        }

        return hash_hmac('sha1', $data, $string);
    }



    /**
     * @return string
     */
    public function getPaymentOrderId(): string
    {
        return $this->order;
    }

    /**
     * @return double
     */
    public function getPaymentAmount()
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getPaymentDescription()
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->email;
    }
}
