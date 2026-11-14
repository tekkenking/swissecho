<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Routes\Sms\SmsRoute;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Swissecho is bound as a container singleton, so one instance serves every
 * send in a process. These tests pin down that nothing from one send can
 * reach the next.
 */
class SendStateIsolationTest extends TestCase
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

        //The whatsapp leg of this test goes through the default gateway,
        //kudisms, which now requires the template code registered on the
        //account rather than the placeholder it used to send.
        $app['config']->set(
            'swissecho.routes_options.whatsapp.gateway_options.kudisms.template_code',
            'test_template'
        );
    }

    private function resolvedRoute(Swissecho $swissecho): object
    {
        $prop = (new \ReflectionClass($swissecho))->getProperty('initRoute');
        $prop->setAccessible(true);

        return $prop->getValue($swissecho);
    }

    private function resolvedGateway(Swissecho $swissecho): string
    {
        $initRoute = $this->resolvedRoute($swissecho);

        $prop = (new \ReflectionClass($initRoute))->getProperty('gateway');
        $prop->setAccessible(true);

        return $prop->getValue($initRoute);
    }

    public function test_an_explicit_gateway_does_not_leak_into_the_next_send(): void
    {
        $swissecho = new Swissecho();

        $swissecho->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('2348012345678')->content('first')->place('nga');
        })->gateway('termii')->go();

        //No gateway named here, and 'gha' is configured to use 'wirepick'.
        //If the previous gateway leaked it would also be treated as an
        //explicit choice, which skips place-based routing entirely.
        $swissecho->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('233501234567')->content('second')->place('gha');
        })->go();

        $this->assertSame('wirepick', $this->resolvedGateway($swissecho));
    }

    public function test_swissecho_routes_does_not_leak_to_the_next_notification(): void
    {
        $swissecho = new Swissecho();

        $restricted = new class extends Notification {
            public function swissechoRoutes($notifiable): array
            {
                return ['whatsapp'];
            }

            public function toWhatsapp($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->to('2348012345678')->content('first')->place('nga');
            }
        };

        $plain = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->to('2348012345678')->content('second')->place('nga');
            }
        };

        $notifiable = new class {
            public string $phone = '08012345678';
        };

        $swissecho->send($notifiable, $restricted);

        //$plain declares no swissechoRoutes(), so it must fall back to every
        //channel. If ['whatsapp'] leaked, toSms() is never found and this
        //notification is silently never sent.
        $swissecho->send($notifiable, $plain);

        $this->assertInstanceOf(SmsRoute::class, $this->resolvedRoute($swissecho));
    }
}
