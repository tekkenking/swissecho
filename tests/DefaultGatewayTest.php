<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * The route config has always been able to carry a 'gateway' key, but
 * nothing read it: getDefaultGateway() went straight to array_key_first(),
 * so the default depended on the order gateways were listed in.
 */
class DefaultGatewayTest extends TestCase
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

        //A place that names no gateway of its own, so the route default decides.
        $app['config']->set('swissecho.routes_options.sms.places.tst', ['phonecode' => '1']);
    }

    private function resolvedGateway(Swissecho $swissecho): string
    {
        $prop = (new \ReflectionClass($swissecho))->getProperty('initRoute');
        $prop->setAccessible(true);
        $initRoute = $prop->getValue($swissecho);

        $gateway = (new \ReflectionClass($initRoute))->getProperty('gateway');
        $gateway->setAccessible(true);

        return $gateway->getValue($initRoute);
    }

    private function send(string $place = 'tst'): Swissecho
    {
        $swissecho = new Swissecho();

        $swissecho->route('sms', function (SwissechoMessage $ms) use ($place) {
            return $ms->to('8012345678')->sender('MyApp')->content('hello')->place($place);
        })->go();

        return $swissecho;
    }

    public function test_the_routes_declared_gateway_is_used(): void
    {
        config(['swissecho.routes_options.sms.gateway' => 'montnets']);

        //array_key_first() would give 'termii' here.
        $this->assertSame('montnets', $this->resolvedGateway($this->send()));
    }

    public function test_it_falls_back_to_the_first_gateway_listed(): void
    {
        config(['swissecho.routes_options.sms.gateway' => null]);

        $this->assertSame('termii', $this->resolvedGateway($this->send()));
    }

    public function test_a_place_gateway_still_wins_over_the_route_default(): void
    {
        config(['swissecho.routes_options.sms.gateway' => 'montnets']);

        //'nga' is configured for nigerianbulksms. Geo-routing must still win,
        //otherwise the places feature would be pointless.
        $this->assertSame('nigerianbulksms', $this->resolvedGateway($this->send('nga')));
    }

    public function test_the_whatsapp_route_uses_its_declared_gateway(): void
    {
        //directwhatsapp is listed first in gateway_options, so array_key_first()
        //returns it; the block declares kudisms, which must win.
        $swissecho = new Swissecho();

        config(['swissecho.routes_options.whatsapp.places.tst' => ['phonecode' => '1']]);
        config(['swissecho.routes_options.whatsapp.gateway_options.kudisms.template_code' => 'test_template']);

        $swissecho->route('whatsapp', function (SwissechoMessage $ms) {
            return $ms->to('8012345678')->sender('MyApp')->content('hello')->place('tst');
        })->go();

        $this->assertSame('kudisms', $this->resolvedGateway($swissecho));
    }

    public function test_an_unknown_gateway_names_the_alternatives(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('the sms route has no gateway named "nope"');

        (new Swissecho())->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('8012345678')->sender('MyApp')->content('hello')->place('tst');
        })->gateway('nope')->go();
    }
}
