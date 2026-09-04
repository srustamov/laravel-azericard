<?php

declare(strict_types=1);

namespace Srustamov\Azericard;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Srustamov\Azericard\Contracts\ClientContract;
use Srustamov\Azericard\Contracts\SignatureGeneratorContract;

class AzericardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/azericard.php', 'azericard');

        $this->app->bind(ClientContract::class, function (): ClientContract {
            $config = $this->config();

            return (new Client(
                timeout: (int) ($config[Options::TIMEOUT] ?? 10),
                retryTimes: (int) ($config[Options::RETRY_TIMES] ?? 1),
                retrySleep: (int) ($config[Options::RETRY_SLEEP] ?? 200),
                verifySsl: (bool) ($config[Options::VERIFY_SSL] ?? true),
            ))->setDebug((bool) ($config[Options::DEBUG] ?? false));
        });

        $this->app->singleton(SignatureGeneratorContract::class, fn(): SignatureGeneratorContract => new SignatureGenerator($this->config()['keys'] ?? []));

        $this->app->bind(Azericard::class, fn(): Azericard => new Azericard(
            $this->app->make(ClientContract::class),
            $this->app->make(SignatureGeneratorContract::class),
            new Options($this->config()),
        ));

        $this->app->alias(Azericard::class, 'azericard');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/azericard.php' => config_path('azericard.php'),
            ], ['config', 'azericard-config']);
        }
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return $this->app->make(Repository::class)->get('azericard', []);
    }
}
