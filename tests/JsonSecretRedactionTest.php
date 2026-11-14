<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Gateways build three payload shapes: an array, a query string, and JSON.
 * Redaction originally handled the first two, so the gateways returning
 * json_encode() - Termii SMS and Montnets - dispatched their credentials in
 * clear through AfterSend, which the README tells people to log.
 */
class JsonSecretRedactionTest extends TestCase
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
    }

    private function requestPayloadFor(string $gateway): mixed
    {
        $insight = [];
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$insight) {
            $insight = $event->insightPayload;
        });

        (new Swissecho())->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('08012345678')->sender('MyApp')->content('hello');
        })->gateway($gateway)->go();

        return $insight['request'] ?? null;
    }

    public function test_the_termii_api_key_is_masked(): void
    {
        config(['swissecho.routes_options.sms.gateway_options.termii.auth.api_key' => 'TLlive_SUPERSECRET']);

        $payload = $this->requestPayloadFor('termii');

        $this->assertStringNotContainsString('TLlive_SUPERSECRET', (string) $payload);
        $this->assertStringContainsString('[redacted]', (string) $payload);

        //Everything that is not a credential must survive, so the record of
        //what was sent stays useful.
        $this->assertStringContainsString('2348012345678', (string) $payload);
        $this->assertStringContainsString('hello', (string) $payload);
    }

    public function test_the_montnets_password_is_masked(): void
    {
        config([
            'swissecho.routes_options.sms.gateway_options.montnets.auth.username' => 'acme',
            'swissecho.routes_options.sms.gateway_options.montnets.auth.password' => 'montnets-secret',
            'swissecho.routes_options.sms.places.nga.gateway' => 'montnets',
        ]);

        $payload = $this->requestPayloadFor('montnets');

        $this->assertStringNotContainsString('montnets-secret', (string) $payload);
        $this->assertStringContainsString('[redacted]', (string) $payload);
    }

    public function test_the_payload_is_still_valid_json(): void
    {
        config(['swissecho.routes_options.sms.gateway_options.termii.auth.api_key' => 'TLlive_SUPERSECRET']);

        $payload = $this->requestPayloadFor('termii');

        //Masking must not corrupt the shape, or a listener parsing it breaks.
        $this->assertIsArray(json_decode((string) $payload, true));
    }
}
