<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class GatewayFromPlaceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Use mock/non-live mode so no real HTTP calls are made
        $app['config']->set('swissecho.live', false);
        $app['config']->set('swissecho.fake', 'log');
        $app['config']->set('app.env', 'local');
    }

    private function notifiable(): object
    {
        return new class {
            public string $phone = '08012345678';
        };
    }

    private function smsNotification(): Notification
    {
        return new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->line('123456')->place('gha');
            }
        };
    }

    /**
     * @throws \ReflectionException
     */
    private function resolvedGateway(\Tekkenking\Swissecho\Swissecho $swissecho): string
    {
        $reflection = new \ReflectionClass($swissecho);
        $initRouteProp = $reflection->getProperty('initRoute');
        $initRouteProp->setAccessible(true);
        $initRoute = $initRouteProp->getValue($swissecho);

        $routeReflection = new \ReflectionClass($initRoute);
        $gatewayProp = $routeReflection->getProperty('gateway');
        $gatewayProp->setAccessible(true);

        return $gatewayProp->getValue($initRoute);
    }

    public function test_it_uses_the_gateway_configured_for_the_selected_place(): void
    {
        $swissecho = new \Tekkenking\Swissecho\Swissecho();

        $swissecho->send($this->notifiable(), $this->smsNotification());

        // 'gha' is configured to use the 'wirepick' gateway, not the
        // route's default gateway ('termii').
        $this->assertSame('wirepick', $this->resolvedGateway($swissecho));
    }

    public function test_it_uses_the_default_gateway_when_the_place_uses_the_default_gateway(): void
    {
        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->line('123456')->place('nga');
            }
        };

        $swissecho = new \Tekkenking\Swissecho\Swissecho();
        $swissecho->send($this->notifiable(), $notification);

        // 'nga' is configured to use 'nigerianbulksms'.
        $this->assertSame('nigerianbulksms', $this->resolvedGateway($swissecho));
    }

    public function test_it_throws_when_a_place_gateway_is_missing_from_the_route_gateway_options(): void
    {
        $this->app['config']->set('swissecho.routes_options.sms.places.nga.gateway', 'missing');

        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->line('123456')->place('nga');
            }
        };

        $swissecho = new \Tekkenking\Swissecho\Swissecho();

        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage("Swissecho: Gateway 'missing' configured for place 'nga' is not configured for the 'sms' route.");

        $swissecho->send($this->notifiable(), $notification);
    }

    public function test_explicit_gateway_selection_still_overrides_the_place_gateway(): void
    {
        $swissecho = (new \Tekkenking\Swissecho\Swissecho())->gateway('termii');

        $swissecho->send($this->notifiable(), $this->smsNotification());

        // Even though 'gha' is configured for 'wirepick', an explicitly
        // requested gateway should take precedence.
        $this->assertSame('termii', $this->resolvedGateway($swissecho));
    }
}
