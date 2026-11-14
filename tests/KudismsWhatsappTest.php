<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\Kudisms\KudismsWhatsapp;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class KudismsWhatsappTest extends TestCase
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

        $base = 'swissecho.routes_options.whatsapp.gateway_options.kudisms.';
        $app['config']->set($base.'url', 'https://my.kudisms.net/api/whatsapp');
        $app['config']->set($base.'auth.api_key', 'kudi-key');
        $app['config']->set($base.'template_code', 'order_update_v3');
    }

    private function gateway(array $overrides = [], string $to = '2348012345678'): object
    {
        $config = array_replace_recursive([
            'url'           => 'https://my.kudisms.net/api/whatsapp',
            'template_code' => 'order_update_v3',
            'auth'          => ['api_key' => 'kudi-key'],
        ], $overrides);

        $message = (new SwissechoMessage())->to($to)->sender('MyApp')->line('Your order has shipped');

        return new class($config, $message) extends KudismsWhatsapp {
            public function buildFor(string $recipient): array
            {
                $this->currentRecipient = $recipient;

                return $this->init();
            }
        };
    }

    public function test_the_template_code_comes_from_config(): void
    {
        $payload = $this->gateway()->buildFor('2348012345678');

        //Was hardcoded to '2147483647', a placeholder no account has.
        $this->assertSame('order_update_v3', $payload['template_code']);
        $this->assertSame('kudi-key', $payload['token']);
        $this->assertSame('2348012345678', $payload['recipient']);
        $this->assertSame('Your order has shipped', $payload['parameters']);
    }

    public function test_a_missing_template_code_is_reported(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('KUDISMS_TEMPLATE_CODE');

        $this->gateway(['template_code' => null])->buildFor('2348012345678');
    }

    public function test_it_sends_one_request_per_recipient(): void
    {
        $events = [];

        Event::listen(AfterSend::class, function (AfterSend $event) use (&$events) {
            $events[] = $event->formattedResponse['to'];
        });

        (new Swissecho())->route('whatsapp', function (SwissechoMessage $ms) {
            return $ms->to('08012345678,08022222222')->sender('MyApp')->content('hello')->place('nga');
        })->gateway('kudisms')->go();

        //Previously $this->to[0] sent to the first number only, while the
        //event still reported both as delivered.
        $this->assertSame(['2348012345678', '2348022222222'], $events);
    }
}
