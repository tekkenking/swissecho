<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class SlackTelegramHonestyTest extends TestCase
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
    }

    private function send(string $route, callable $build): array
    {
        $captured = [];
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event->formattedResponse;
        });

        (new Swissecho())->route($route, $build)->go();

        return $captured;
    }

    public function test_a_bot_token_with_no_channel_fails_in_mock_mode_too(): void
    {
        config([
            'swissecho.routes_options.slack.auth.token' => 'xoxb-token',
            'swissecho.routes_options.slack.auth.webhook' => null,
            'swissecho.routes_options.slack.default_channel' => null,
        ]);

        //This used to report success locally and throw in production,
        //because the channel check sat past the mock branch.
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('a channel is required with a bot token');

        $this->send('slack', fn (SwissechoMessage $ms) => $ms->content('hello'));
    }

    public function test_a_requested_channel_is_not_reported_when_a_webhook_sends_it(): void
    {
        config([
            'swissecho.routes_options.slack.auth.webhook' => 'https://hooks.slack.com/services/T/B/x',
            'swissecho.routes_options.slack.auth.token' => null,
        ]);

        $response = $this->send('slack', fn (SwissechoMessage $ms) => $ms->to('#alerts')->content('hello'));

        //A webhook posts only to its own channel, so '#alerts' was never
        //sent. Reporting it as the recipient was a lie in the audit trail.
        $this->assertNull($response['to']);
    }

    public function test_the_bot_token_wins_when_a_channel_is_requested(): void
    {
        config([
            'swissecho.routes_options.slack.auth.webhook' => 'https://hooks.slack.com/services/T/B/x',
            'swissecho.routes_options.slack.auth.token' => 'xoxb-token',
        ]);

        $response = $this->send('slack', fn (SwissechoMessage $ms) => $ms->to('#alerts')->content('hello'));

        //With both configured the webhook used to win unconditionally, so a
        //targeted send went to the wrong channel.
        $this->assertSame('#alerts', $response['to']);
    }

    public static function gatewayRoutes(): array
    {
        return ['slack' => ['slack'], 'telegram' => ['telegram']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('gatewayRoutes')]
    public function test_naming_a_gateway_is_rejected(string $route): void
    {
        config([
            'swissecho.routes_options.slack.auth.webhook' => 'https://hooks.slack.com/services/T/B/x',
            'swissecho.routes_options.telegram.auth.token' => 'bot-token',
        ]);

        //Every other route raises a clear exception for a gateway it has no
        //config for; these two silently ignored it.
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('has no gateways');

        (new Swissecho())->route($route, function (SwissechoMessage $ms) {
            return $ms->to('#alerts')->content('hello');
        })->gateway('termii')->go();
    }
}
