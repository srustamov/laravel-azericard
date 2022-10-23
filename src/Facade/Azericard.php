<?php

namespace Srustamov\Azericard\Facade;

use Illuminate\Support\Facades\Facade;
use Srustamov\Azericard\Options;

/**
 * Class Azericard
 * @package Srustamov\Azericard\Facade
 *
 *
 * @method static \Srustamov\Azericard\Azericard setAmount(float|int $amount)
 * @method static \Srustamov\Azericard\Azericard setOrder(string $order)
 * @method static \Srustamov\Azericard\Azericard setDebug(bool $debug)
 * @method static \Srustamov\Azericard\Azericard setOptions(Options $options)
 * @method static boolean checkout($request)
 * @see \Srustamov\Azericard\Azericard
 */
class Azericard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Srustamov\Azericard\Azericard::class;
    }
}
