<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class AuditCloseOutTest extends TestCase
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
        $app['config']->set('swissecho.routes_options.sms.default_place', 'nga');
        $app['config']->set('swissecho.routes_options.slack.auth.webhook', 'https://hooks.slack.com/services/T/B/x');
        $app['config']->set('swissecho.routes_options.telegram.auth.token', 'bot-token');
    }

    private function capture(array &$into): void
    {
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$into) {
            $into[] = ['formatted' => $event->formattedResponse, 'insight' => $event->insightPayload];
        });
    }

    public function test_a_direct_send_resolves_the_number_from_the_notifiable(): void
    {
        $sent = [];
        $this->capture($sent);

        $notifiable = new class {
            public function routeNotificationPhone(): string
            {
                return '08055544433';
            }
        };

        //makeNotifiable() had no effect on a direct send: only the
        //notification path consulted the notifiable, so this threw
        //'Invalid phone number' even though the notifiable could answer.
        (new Swissecho())->makeNotifiable($notifiable)->route('sms', function (SwissechoMessage $ms) {
            return $ms->content('hello')->place('nga');
        })->gateway('termii')->go();

        $this->assertSame(['2348055544433'], $sent[0]['formatted']['to']);
    }

    public function test_a_direct_send_still_prefers_an_explicit_recipient(): void
    {
        $sent = [];
        $this->capture($sent);

        $notifiable = new class {
            public string $phone = '08055544433';
        };

        (new Swissecho())->makeNotifiable($notifiable)->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('08012345678')->content('hello')->place('nga');
        })->gateway('termii')->go();

        $this->assertSame(['2348012345678'], $sent[0]['formatted']['to']);
    }

    public static function httpRoutes(): array
    {
        return [
            'slack'    => ['slack', '#alerts', 'text'],
            'telegram' => ['telegram', '-100999', 'chat_id'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('httpRoutes')]
    public function test_a_mocked_send_carries_its_request_payload(string $route, string $to, string $expectedKey): void
    {
        $sent = [];
        $this->capture($sent);

        (new Swissecho())->route($route, function (SwissechoMessage $ms) use ($to) {
            return $ms->to($to)->content('hello');
        })->go();

        //insightPayload['request'] used to stay [] for these two routes in
        //mock mode, so anything auditing mock sends saw nothing.
        $this->assertNotSame([], $sent[0]['insight']['request']);
        $this->assertArrayHasKey($expectedKey, $sent[0]['insight']['request']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('httpRoutes')]
    public function test_a_sender_is_not_reported_for_routes_that_cannot_send_one(string $route, string $to, string $unusedKey): void
    {
        $sent = [];
        $this->capture($sent);

        (new Swissecho())->route($route, function (SwissechoMessage $ms) use ($to) {
            return $ms->to($to)->from('AuditBot')->content('hello');
        })->go();

        //Neither Slack nor Telegram has a sender field in its payload, so
        //->from() is accepted and never sent. Reporting it made the event
        //claim an identity that never left the process.
        $this->assertNull($sent[0]['formatted']['from']);
    }
}
