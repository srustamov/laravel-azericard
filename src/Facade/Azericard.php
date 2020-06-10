<?php
namespace Srustamov\Azericard\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * Class Azericard
 * @package Srustamov\Azericard\Facade
 *
 *
 * @method static \Srustamov\Azericard\Azericard init(array $parameters)
 * @method static string paymentForm()
 * @method static \Srustamov\Azericard\Azericard setCallBackParameters($params)
 * @method static \Srustamov\Azericard\Azericard handleCallback()
 * @method static void completeCheckout()
 *
 * @see \Srustamov\Azericard\Azericard
 */

class Azericard extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Srustamov\Azericard\Azericard::class;
    }
}