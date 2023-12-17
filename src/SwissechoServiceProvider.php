<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho;

use Illuminate\Support\ServiceProvider;

class SwissechoServiceProvider extends ServiceProvider
{


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/swissecho.php' => config_path('swissecho.php'),
        ], 'swissecho-config');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/swissecho.php', 'swissecho'
        );


        $this->app->singleton('swissecho', function ($app) {
            return new Swissecho();
        });

    }

}
