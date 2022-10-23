<?php

namespace Srustamov\Azericard;

use Illuminate\Support\ServiceProvider;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

/**
 * Class AzericardServiceProvider
 * @package Srustamov\Azericard
 */
class AzericardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/azericard.php', 'azericard');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/azericard.php' => config_path('azericard.php'),
            ], 'config');

        }
    }

    public function register(): void
    {
        $this->app->bind(ClientContract::class, static function () {
            return new Client();
        });

        $this->app->bind(SignatureGeneratorContract::class, static function () {
            return new SignatureGenerator(config('azericard.sign', ''));
        });
    }
}
