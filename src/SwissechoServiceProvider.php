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
        $this->app->make(\Illuminate\Notifications\ChannelManager::class)
            ->extend('swissecho', fn () => $this->app->make(Swissecho::class));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/swissecho.php' => $this->app->configPath('swissecho.php'),
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
