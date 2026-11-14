<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Swissecho is a container singleton, so one instance serves every send.
 * Nothing identifying a recipient may survive the send that set it.
 */
class NotifiableIsolationTest extends TestCase
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
        $app['config']->set('swissecho.routes_options.sms.default_place', 'nga');
        $app['config']->set('swissecho.routes_options.whatsapp.gateway_options.kudisms.template_code', 'test_template');
        $app['config']->set('swissecho.routes_options.whatsapp.places.nga', ['phonecode' => '+234', 'gateway' => 'kudisms']);
        //SWISSECHO_DEFAULT_PLACE feeds every route's default_place in the
        //real config; set both here since we are going through config().
        $app['config']->set('swissecho.routes_options.whatsapp.default_place', 'nga');
    }

    private function listen(array &$into): void
    {
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$into) {
            $into[] = $event->formattedResponse;
        });
    }

    public function test_a_direct_notifiable_does_not_capture_the_next_send(): void
    {
        $sent = [];
        $this->listen($sent);

        $alice = new class { public string $phone = '08011111111'; };
        $bob   = new class { public string $phone = '08099999999'; };

        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('for bob');
            }
        };

        $swissecho = new Swissecho();

        $swissecho->makeNotifiable($alice)->route('sms', function (SwissechoMessage $ms) {
            return $ms->content('for alice')->to('08011111111');
        })->go();

        $swissecho->send($bob, $notification);

        //makeNotifiable() used to survive the send that used it, and go()
        //prefers the direct notifiable - so Bob's message went to Alice.
        $this->assertSame(['2348011111111'], $sent[0]['to']);
        $this->assertSame('for alice', $sent[0]['body']);

        $this->assertSame(['2348099999999'], $sent[1]['to'], 'the second message must reach Bob');
        $this->assertSame('for bob', $sent[1]['body']);
    }

    public function test_a_bare_go_does_not_resend_the_previous_notification(): void
    {
        $sent = [];
        $this->listen($sent);

        $alice = new class { public string $phone = '08011111111'; };

        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('first body');
            }
        };

        $swissecho = new Swissecho();
        $swissecho->send($alice, $notification);

        $this->assertCount(1, $sent);

        //This used to send 'first body' a second time: a duplicate billed
        //message from a call that set nothing at all.
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('Nothing to send');

        $swissecho->route('sms')->go();
    }

    public function test_a_notification_can_choose_its_gateway(): void
    {
        $sent = [];
        $this->listen($sent);

        $notifiable = new class { public string $phone = '08011111111'; };

        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                //Documented in the README, both in the method table and as
                //the way to use a gateway you wrote yourself.
                return (new SwissechoMessage())->content('hi')->gateway('termii');
            }
        };

        (new Swissecho())->send($notifiable, $notification);

        //The place 'nga' maps to nigerianbulksms, which used to win silently.
        $this->assertSame('termii', $sent[0]['gateway']);
    }

    public function test_a_multi_channel_notification_still_reaches_every_channel(): void
    {
        $sent = [];
        $this->listen($sent);

        $notifiable = new class { public string $phone = '08011111111'; };

        $notification = new class extends Notification {
            public function swissechoRoutes($notifiable): array
            {
                return ['sms', 'whatsapp'];
            }

            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('sms body');
            }

            public function toWhatsapp($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('whatsapp body');
            }
        };

        (new Swissecho())->send($notifiable, $notification);

        //makeRoutes() reads $this->notification once per via, so clearing it
        //must happen after the loop, not inside go().
        $this->assertSame(['sms', 'whatsapp'], array_column($sent, 'route'));
    }
}
