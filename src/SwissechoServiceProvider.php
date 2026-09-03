<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho;

use Illuminate\Support\ServiceProvider;

class SwissechoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $app = $this->app;

        $app->make(\Illuminate\Notifications\ChannelManager::class)
            ->extend('swissecho', fn () => $app->make(Swissecho::class));

        if ($app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/swissecho.php' => $app->configPath('swissecho.php'),
            ], 'swissecho-config');
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/swissecho.php',
            'swissecho'
        );

        $this->app->singleton('swissecho', function () {
            return new Swissecho();
        });
    }
}
