<?php

namespace Srustamov\Azericard;

use Illuminate\Support\ServiceProvider;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

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

        $this->app->singleton(SignatureGeneratorContract::class, static function () {
            return new SignatureGenerator(app('config')->get('azericard.keys', []));
        });

        $this->app->bind(Azericard::class, static function () {
            return new Azericard(
                app(ClientContract::class),
                app(SignatureGeneratorContract::class),
                new Options(app('config')->get('azericard', []))
            );
        });
    }
}
