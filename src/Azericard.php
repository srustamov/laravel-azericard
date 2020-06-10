<?php


namespace Srustamov\Azericard;

use Monolog\Logger;
use Illuminate\Support\Collection;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Srustamov\Azericard\GenerateSign;
use Srustamov\Azericard\Exceptions\AzericardException;
use Srustamov\Azericard\Exceptions\FailedTransactionException;

/**
 * Class Azericard
 * @package Srustamov\Azericard
 */
class Azericard
{
    use GenerateSign;

    protected $debug = false;

    protected $config = [];

    protected $logPath = null;

    protected $irKey = 'INTREF';

    protected $callbackParameters = [];

    protected $form_custom_params = [];

    protected $callbackRequiredParameters = [
        'TERMINAL',
        'TRTYPE',
        'ORDER',
        'AMOUNT',
        'CURRENCY',
        'ACTION',
        'RC',
        'APPROVAL',
        'RRN',
        'TIMESTAMP',
        'NONCE',
        'P_SIGN',
    ];

    protected $paymentFormRequiredParameters = [
        'URL',
        'AMOUNT',
        'CURRENCY',
        'ORDER',
        'DESC',
        'MERCH_NAME',
        'MERCH_URL',
        'LANG',
        'TERMINAL',
        'EMAIL',
        'TRTYPE',
        'COUNTRY',
        'MERCH_GMT',
        'BACKREF',
        'KEY_FOR_SIGN',
    ];

    protected $reversalFormRequiredParameters = [
        'URL',
        'AMOUNT',
        'CURRENCY',
        'ORDER',
        'RRN',
        'TERMINAL',
        'TRTYPE',
        'KEY_FOR_SIGN',
    ];


    /**
     * Azericard constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new AzericardException('Azericard configs required');
        }

        $this->config = collect($config);

        $this->debug = $this->config->get('debug');

        $this->irKey = $this->debug ? 'INT_REF' : 'INTREF';

        $this->config['URL'] = $this->debug
            ? $this->config['urls']['test']
            : $this->config['urls']['production'];

        $this->config['BACKREF'] = $this->config['urls']['backref'];

        $this->setLogPath($this->config->get('log_path'));

        $this->callbackRequiredParameters[] = $this->irKey;
        $this->reversalFormRequiredParameters[] = $this->irKey;

        unset($config);
    }

    public function init(array $parameters)
    {
        $this->config = $this->config->merge($parameters);

        return $this;
    }


    /**
     * @return $this
     */
    public function test(): self
    {
        $this->debug = true;

        return $this;
    }


    /**
     * @param string $path
     * @return Azericard
     */
    public function setLogPath(string $path): self
    {
        if (!File::exists(File::dirname($path))) {
            File::makeDirectory(File::dirname($path));
        }

        $this->logPath = $path;

        return $this;
    }

    /**
     * @param array $parameters
     * @return Azericard
     */
    public function setCallBackParameters($parameters = []): self
    {
        $this->callbackParameters = $parameters;

        return $this;
    }

    /**
     * @return array
     */
    public function getCallBackParameters(): array
    {
        return $this->callbackParameters;
    }

    /**
     * @param array $parameters
     * @return Azericard
     */
    public function formWithParams(array $parameters): self
    {
        $this->form_custom_params = $params;

        return $this;
    }

    /**
     * @return string
     */
    public function paymentForm(): string
    {
        $this->validateFormParameters($this->paymentFormRequiredParameters);

        $params = $this->config;

        $params->put('ORDER',str_pad($params->get('ORDER'), 6, '0', STR_PAD_LEFT));

        $params->put('P_SIGN', $this->getSignHash($this->generatePSignForPaymentForm($params)));

        return view('azericard::payment-form', array_merge(
          [
            'params' => $params->toArray(),
          ],
          $this->form_custom_params
          ))->toHtml();
    }


    /**
     * @return string
     */
    public function reversalForm(): string
    {
        $this->validateFormParameters($this->reversalFormRequiredParameters);

        $params = $this->config;

        $params->put('ORDER', str_pad($params['ORDER'], 6, '0', STR_PAD_LEFT));

        $params->put('P_SIGN',$this->getSignHash($this->generatePSignForReversalForm($params)));

        return view(
          'azericard::reversal-form',
          array_merge(
            [
                'params' => $params->toArray(),
                'irKeyName' => $this->irKey,
                'irValue' => $params->get($this->irKey)
            ],
            $this->form_custom_params
         )
        )->toHtml();
    }

    /**
     * @return Azericard
     */
    public function handleCallback(): self
    {
        $parameters = $this->getCallBackParameters();

        if ($this->logPath !== null) {
            $this->logCallback();
        }

        if (empty($parameters)) {
            throw new AzericardException('Callback parameters required');
        }

        foreach ($this->callbackRequiredParameters as $key) {
            if (!array_key_exists($key, $parameters)) {
                throw new AzericardException(sprintf('Azericard callback [%s] parameter required', $key));
            }
        }


        if ($parameters['ACTION'] !== '0' || $parameters['RC'] !== '00') {
            throw new FailedTransactionException($parameters['RC']);
        }

        $this->validateHash(
            $this->getSignHash($this->generateSignForHandleCallback($parameters)),
            $parameters['P_SIGN']
        );

        return $this;
    }



    /**
     * @return bool|null
     */
    public function completeCheckout(): bool
    {
        $parameters = $this->getCallBackParameters();

        if (empty($parameters)) {
            throw new AzericardException('Callback parameters is empty');
        }

        if ($parameters['ACTION'] !== '0' || $parameters['RC'] !== '00') {
            throw new FailedTransactionException($parameters['RC']);
        }

        $form_params = collect($parameters)->only([
            'AMOUNT',
            'CURRENCY',
            'ORDER',
            'RRN',
            'TERMINAL',
            'RRN',
            'NONCE',
            'TIMESTAMP'
        ])
            ->put(
                'P_SIGN',$this->getSignHash($this->generateSignForCheckout($parameters))
            )->toArray();

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Accept' => 'text/html',
                'Content-Type' => 'text/html',
            ])
            ->post($this->config['URL'], compact('form_params'));

        if ($response->ok()) {
            if ((string)$response->body() === '0') {
                return true;
            }

            throw new FailedTransactionException((string)$response->body());
        }

        return false;

    }


    protected function logCallback(): void
    {
        $logPath = rtrim($this->logPath, DIRECTORY_SEPARATOR);

        File::ensureDirectoryExists($logPath);

        $this->log(
            $logPath . DIRECTORY_SEPARATOR . 'AzeriCard-' . now()->format('Y-m-d') . '.log',
            'Azericard Callback Log',
            $this->getCallBackParameters()
        );
    }


    /**
     * @param $path
     * @param string $message
     * @param array $context
     */
    protected function log($path, $message = '', $context = []): void
    {
        $orderLog = new Logger('Azericard');
        $orderLog->pushHandler(new StreamHandler($path, Logger::INFO));
        $orderLog->info($message, $context);
    }

    /**
     * @param $string
     * @return string
     */
    public function getSignHash($string): string
    {
        return hash_hmac('sha1', $string, hex2bin($this->config['KEY_FOR_SIGN']));
    }

    /**
     * @param $hash
     * @param $request
     */
    protected function validateHash($hash, $request): void
    {
        if (strtoupper($hash) !== strtoupper($request)) {
            throw new AzericardException('Wrong hash value');
        }
    }


    protected function validateFormParameters($parameters)
    {
        foreach ($parameters as $key) {
            if (!$this->config->has($key)) {
                throw new AzericardException(sprintf('Azericard form [%s] parameter required', $key));
            }
        }
    }


}
