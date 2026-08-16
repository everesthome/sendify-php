<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Laravel;

use EverestHome\Sendify\Http\ClientInterface;
use EverestHome\Sendify\Http\CurlClient;
use EverestHome\Sendify\Sendify;
use EverestHome\Sendify\SendifyManager;
use Illuminate\Support\ServiceProvider;

class SendifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/sendify.php', 'sendify');

        $this->app->singleton(ClientInterface::class, static fn () => new CurlClient(
            'sendify-php (Laravel)'
        ));

        $this->app->singleton(SendifyManager::class, fn ($app) => new SendifyManager(
            (array) $app['config']->get('sendify', []),
            $app->make(ClientInterface::class),
        ));

        $this->app->alias(SendifyManager::class, 'sendify');

        // Inyección por tipo: function __construct(Sendify $sendify)
        $this->app->bind(Sendify::class, fn ($app) => $app->make(SendifyManager::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/sendify.php' => $this->app->configPath('sendify.php'),
            ], 'sendify-config');
        }
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [SendifyManager::class, Sendify::class, ClientInterface::class, 'sendify'];
    }
}
