<?php


namespace Srustamov\Azericard;


use Illuminate\Support\Collection;

/**
 * Trait GenerateSign
 * @package Srustamov\Azericard
 */
trait GenerateSign
{


    /**
     * @param array $params
     * @return string
     */
    protected function generatePSignForPaymentForm($params): string
    {
        return strlen($params['AMOUNT']) . $params['AMOUNT']
            . strlen($params['CURRENCY']) . $params['CURRENCY']
            . strlen($params['ORDER']) . $params['ORDER']
            . strlen($params['DESC']) . $params['DESC']
            . strlen($params['MERCH_NAME']) . $params['MERCH_NAME']
            . strlen($params['MERCH_URL']) . $params['MERCH_URL']
            . ($this->debug ? '' : '-')
            . strlen($params['TERMINAL']) . $params['TERMINAL']
            . strlen($params['EMAIL']) . $params['EMAIL']
            . strlen($params['TRTYPE']) . $params['TRTYPE']
            . strlen($params['COUNTRY']) . $params['COUNTRY']
            . strlen($params['MERCH_GMT']) . $params['MERCH_GMT']
            . strlen($params['TIMESTAMP']) . $params['TIMESTAMP']
            . strlen($params['NONCE']) . $params['NONCE']
            . strlen($params['BACKREF']) . $params['BACKREF'];
    }

    /**
     * @param Collection $params
     * @return string
     */
    protected function generatePSignForReversalForm($params): string
    {
        return strlen($params['ORDER']) . $params['ORDER']
            . strlen($params['AMOUNT']) . $params['AMOUNT']
            . strlen($params['CURRENCY']) . $params['CURRENCY']
            . strlen($params['RRN']) . $params['RRN']
            . strlen($params[$this->irKey]) . $params[$this->irKey]
            . strlen($params['TRTYPE']) . $params['TRTYPE']
            . strlen($params['TERMINAL']) . $params['TERMINAL']
            . strlen($params['TIMESTAMP']) . $params['TIMESTAMP']
            . strlen($params['NONCE']) . $params['NONCE'];
    }


    /**
     * @param $parameters
     * @return string
     */
    protected function generateSignForHandleCallback($parameters): string
    {
        return strlen($parameters['TERMINAL']) . $parameters['TERMINAL']
            . strlen($parameters['TRTYPE']) . $parameters['TRTYPE']
            . strlen($parameters['ORDER']) . $parameters['ORDER']
            . strlen($parameters['AMOUNT']) . $parameters['AMOUNT']
            . strlen($parameters['CURRENCY']) . $parameters['CURRENCY']
            . strlen($parameters['ACTION']) . $parameters['ACTION']
            . strlen($parameters['RC']) . $parameters['RC']
            . strlen($parameters['APPROVAL']) . $parameters['APPROVAL']
            . strlen($parameters['RRN']) . $parameters['RRN']
            . strlen($parameters[$this->irKey]) . $parameters[$this->irKey]
            . strlen($parameters['TIMESTAMP']) . $parameters['TIMESTAMP']
            . strlen($parameters['NONCE']) . $parameters['NONCE'];
    }


    /**
     * @param $parameters
     * @return string
     */
    protected function generateSignForCheckout($parameters): string
    {
        return strlen($parameters['ORDER']) . $parameters['ORDER']
            . strlen($parameters['AMOUNT']) . $parameters['AMOUNT']
            . strlen($parameters['CURRENCY']) . $parameters['CURRENCY']
            . strlen($parameters['RRN']) . $parameters['RRN']
            . strlen($parameters[$this->irKey]) . $parameters[$this->irKey]
            . strlen($parameters['TRTYPE']) . $parameters['TRTYPE']
            . strlen($parameters['TERMINAL']) . $parameters['TERMINAL']
            . strlen($parameters['TIMESTAMP']) . $parameters['TIMESTAMP']
            . strlen($parameters['NONCE']) . $parameters['NONCE'];;
    }
}
