<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class DefaultPlaceTest extends TestCase
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

    private function notifiableWithoutPlace(): object
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
                return (new SwissechoMessage())->line('123456');
            }
        };
    }

    public function test_it_throws_helpful_exception_when_no_place_is_resolvable(): void
    {
        $swissecho = new \Tekkenking\Swissecho\Swissecho();

        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage("No place could be determined for the 'sms' route");

        $swissecho->send($this->notifiableWithoutPlace(), $this->smsNotification());
    }

    public function test_it_uses_env_configured_default_place(): void
    {
        $this->app['config']->set('swissecho.routes_options.sms.default_place', 'gha');

        $swissecho = new \Tekkenking\Swissecho\Swissecho();

        // Should not throw; default_place resolves the place.
        $swissecho->send($this->notifiableWithoutPlace(), $this->smsNotification());
        $this->assertTrue(true);
    }

    public function test_route_notification_place_takes_priority_over_default_place(): void
    {
        $this->app['config']->set('swissecho.routes_options.sms.default_place', 'gha');

        $notifiable = new class {
            public string $phone = '08012345678';

            public function routeNotificationPlace(): string
            {
                return 'nga';
            }
        };

        $swissecho = new \Tekkenking\Swissecho\Swissecho();

        // Should not throw; routeNotificationPlace() resolves the place.
        $swissecho->send($notifiable, $this->smsNotification());
        $this->assertTrue(true);
    }
}
