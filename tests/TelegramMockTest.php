<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class TelegramMockTest extends TestCase
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

        //No bot token, as a developer who has not signed up yet.
        $app['config']->set('swissecho.routes_options.telegram.auth.token', null);
    }

    public function test_telegram_mocks_without_a_bot_token(): void
    {
        //The token check used to run before the mock check, so this threw
        //'configure TELEGRAM_BOT_TOKEN' while Slack mocked fine in the same
        //setup. Nothing leaves the app in mock mode, so no token is needed.
        (new Swissecho())->route('telegram', function (SwissechoMessage $ms) {
            return $ms->to('@mychannel')->content('hello');
        })->go();

        $this->assertTrue(true, 'a mocked Telegram send needs no credentials');
    }
}
