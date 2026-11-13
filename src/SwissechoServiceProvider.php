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

        //Only the webhook routes: src/Urls/web.php held a leftover
        //GET hello_swissecho that called dd(), and loading the directory
        //would have published it on every host app.
        $this->loadRoutesFrom(__DIR__ . '/Urls/webhook.php');

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
