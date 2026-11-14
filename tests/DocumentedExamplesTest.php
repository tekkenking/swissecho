<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Runs the README's own send examples verbatim.
 *
 * Every one of them raised 'No place could be determined' on a fresh
 * install, because the quick start set no place and the env block called
 * SWISSECHO_DEFAULT_PLACE optional. Examples that do not run are worse
 * than no examples, so they are pinned here.
 */
class DocumentedExamplesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('swissecho.live', false);
        $app['config']->set('swissecho.fake', 'log');
        $app['config']->set('app.env', 'local');

        //A default sender, as any real install has. Gateways that need one
        //refuse to send without it.
        $app['config']->set('swissecho.sender', 'MyApp');

        //Exactly what the quick start now tells you to set, and nothing more.
        $app['config']->set('swissecho.routes_options.sms.default_place', 'nga');
    }

    public function test_the_quick_start_one_liner(): void
    {
        (new Swissecho())->quick('2348012345678', 'Your OTP code is 1234');

        $this->assertTrue(true, 'quick() must work with only a default place set');
    }

    public function test_the_quick_start_fluent_example(): void
    {
        (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('2348012345678')
                      ->content('Your order has been shipped!');
        })->gateway('termii')->sender('MyApp')->go();

        $this->assertTrue(true);
    }

    public function test_the_full_control_example(): void
    {
        (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('2348012345678, 2348098765432')
                      ->content('Your order has been shipped!')
                      ->line('Track it at https://example.com/track');
        })->to('2348011111111')->sender('MyApp')->gateway('termii')->go();

        $this->assertTrue(true);
    }

    public function test_the_property_based_example(): void
    {
        $sw = new Swissecho();
        $sw->gateway('termii');
        $sw->to = '2348012345678';
        $sw->sender = 'MyApp';
        $sw->message = 'The world is a beautiful place created by GOD';
        $sw->go();

        $this->assertTrue(true);
    }

    public function test_the_gateway_override_example(): void
    {
        (new Swissecho())->gateway('routemobile')->quick('2348012345678', 'Your OTP code is 1234');

        $this->assertTrue(true);
    }
}
