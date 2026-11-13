<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\DirectWhatsapp\DirectWhatsapp;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class DirectWhatsappTest extends TestCase
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

        $base = 'swissecho.routes_options.whatsapp.gateway_options.directwhatsapp.';
        $app['config']->set($base.'phone_number_id', '123456789');
        $app['config']->set($base.'auth.access_token', 'EAAG-system-user-token');
        $app['config']->set($base.'api_version', 'v21.0');
        $app['config']->set($base.'template.name', null);

        //Route Nigeria through this gateway for the end-to-end test.
        $app['config']->set('swissecho.routes_options.whatsapp.places.nga.gateway', 'directwhatsapp');
    }

    private function gateway(array $overrides = [], string $to = '2348012345678'): object
    {
        $config = array_replace_recursive([
            'phone_number_id' => '123456789',
            'api_version'     => 'v21.0',
            'auth'            => ['access_token' => 'EAAG-system-user-token'],
        ], $overrides);

        $message = (new SwissechoMessage())->to($to)->sender('MyApp')->line('Your order has shipped');

        return new class($config, $message) extends DirectWhatsapp {
            public function buildFor(string $recipient): array
            {
                $this->currentRecipient = $recipient;

                return $this->init();
            }

            public function url(): string
            {
                return $this->endpoint();
            }

            public function allRecipients(): array
            {
                return $this->recipients();
            }
        };
    }

    public function test_it_builds_the_documented_cloud_api_endpoint(): void
    {
        $this->assertSame(
            'https://graph.facebook.com/v21.0/123456789/messages',
            $this->gateway()->url()
        );
    }

    public function test_a_configured_url_is_used_as_given(): void
    {
        $this->assertSame(
            'https://proxy.internal/whatsapp',
            $this->gateway(['url' => 'https://proxy.internal/whatsapp'])->url()
        );
    }

    public function test_a_missing_phone_number_id_is_reported(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('WHATSAPP_PHONE_NUMBER_ID');

        $this->gateway(['phone_number_id' => null])->url();
    }

    public function test_it_builds_a_text_message(): void
    {
        $payload = $this->gateway()->buildFor('2348012345678');

        $this->assertSame([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => '2348012345678',
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => 'Your order has shipped',
            ],
        ], $payload);
    }

    public function test_it_builds_a_template_message_when_one_is_configured(): void
    {
        $payload = $this->gateway([
            'template' => ['name' => 'order_update', 'language' => 'en_GB'],
        ])->buildFor('2348012345678');

        $this->assertSame('template', $payload['type']);
        $this->assertSame('order_update', $payload['template']['name']);
        $this->assertSame('en_GB', $payload['template']['language']['code']);
        $this->assertSame(
            'Your order has shipped',
            $payload['template']['components'][0]['parameters'][0]['text']
        );
        $this->assertArrayNotHasKey('text', $payload);
    }

    public function test_it_keeps_every_recipient(): void
    {
        $gateway = $this->gateway([], '2348012345678,2348022222222, ');

        //The old gateway used $this->to[0] and dropped the rest silently.
        $this->assertSame(['2348012345678', '2348022222222'], $gateway->allRecipients());
    }

    public function test_it_sends_one_request_per_recipient(): void
    {
        $events = [];

        Event::listen(AfterSend::class, function (AfterSend $event) use (&$events) {
            $events[] = $event->formattedResponse['to'];
        });

        (new Swissecho())->route('whatsapp', function (SwissechoMessage $ms) {
            return $ms->to('08012345678,08022222222')->sender('MyApp')->content('hello')->place('nga');
        })->gateway('directwhatsapp')->go();

        //One message, one event, one recipient named on each.
        $this->assertSame(['2348012345678', '2348022222222'], $events);
    }
}
