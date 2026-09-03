<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Facades\Swissecho;
use Tekkenking\Swissecho\SwissechoFacade;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class SwissechoFacadeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Swissecho' => Swissecho::class,
        ];
    }

    public function test_facade_resolves_to_swissecho_singleton(): void
    {
        $this->assertInstanceOf(
            \Tekkenking\Swissecho\Swissecho::class,
            Swissecho::getFacadeRoot()
        );

        $this->assertSame(
            $this->app->make('swissecho'),
            Swissecho::getFacadeRoot()
        );
    }

    public function test_deprecated_swissecho_facade_alias_still_resolves(): void
    {
        $this->assertInstanceOf(
            \Tekkenking\Swissecho\Swissecho::class,
            SwissechoFacade::getFacadeRoot()
        );
    }

    public function test_facade_alias_matches_config_app_style_alias(): void
    {
        $this->assertTrue(class_exists(Swissecho::class));
        $this->assertTrue(is_subclass_of(Swissecho::class, \Illuminate\Support\Facades\Facade::class));
    }

    public function test_swissecho_notification_channel_is_registered_without_error(): void
    {
        $channelManager = $this->app->make(\Illuminate\Notifications\ChannelManager::class);
        $channel = $channelManager->driver('swissecho');

        $this->assertInstanceOf(\Tekkenking\Swissecho\Swissecho::class, $channel);
    }

    public function test_config_is_publishable_under_swissecho_config_tag(): void
    {
        $publishes = SwissechoServiceProvider::pathsToPublish(
            SwissechoServiceProvider::class,
            'swissecho-config'
        );

        $this->assertCount(1, $publishes);

        $source = array_key_first($publishes);
        $this->assertSame(
            realpath(__DIR__ . '/../config/swissecho.php'),
            realpath($source)
        );
        $this->assertSame(
            $this->app->configPath('swissecho.php'),
            $publishes[$source]
        );
    }

    public function test_merged_config_matches_package_config_file(): void
    {
        $this->assertSame(
            require __DIR__ . '/../config/swissecho.php',
            $this->app['config']['swissecho']
        );
    }
}
