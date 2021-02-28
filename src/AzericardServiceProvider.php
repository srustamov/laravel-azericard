<?php

namespace Srustamov\Azericard;

use Illuminate\Support\ServiceProvider;
use Srustamov\Azericard\Azericard;

/**
 * Class AzericardServiceProvider
 * @package Srustamov\Azericard
 */
class AzericardServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/azericard.php', 'azericard');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/azericard.php' => config_path('azericard.php'),
            ], 'config');

        }
    }

    /**
     * Register the application services.
     */
    public function register():void
    {
        $this->app->bind(Azericard::class, static function () {
            return new Azericard('AZN');
        });
    }
}
