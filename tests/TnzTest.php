<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz\Tnz;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * The request TNZ's REST API v3.00 documents: a Bearer token, and a flat
 * body with a Destinations list of ToNumber entries.
 */
class TnzTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    private function gateway(array $config = [], ?string $sender = null): Tnz
    {
        $message = (new SwissechoMessage())->to(['6421000001', '6421000002'])->line('hello');

        if ($sender !== null) {
            $message->sender($sender);
        }

        return new Tnz($config + ['auth' => ['token' => 'jwt-token']], $message);
    }

    private function headersOf(Tnz $gateway): array
    {
        return (new \ReflectionMethod($gateway, 'headers'))->invoke($gateway);
    }

    public function test_it_builds_the_v3_body(): void
    {
        $this->assertSame([
            'Message'      => 'hello',
            'Destinations' => [['ToNumber' => '+6421000001'], ['ToNumber' => '+6421000002']],
        ], $this->gateway()->init());
    }

    public function test_a_phone_number_sender_is_sent_as_from_number(): void
    {
        $this->assertSame('61408080909', $this->gateway([], '+61408080909')->init()['FromNumber']);
    }

    public function test_a_name_sender_is_left_out(): void
    {
        //FromNumber only takes a phone number, so a business name must not
        //be sent in it.
        $this->assertArrayNotHasKey('FromNumber', $this->gateway([], 'PartyShop')->init());
    }

    public function test_it_authenticates_with_a_bearer_token(): void
    {
        $this->assertContains('Authorization: Bearer jwt-token', $this->headersOf($this->gateway()));
    }

    public function test_the_old_api_key_setting_is_still_read(): void
    {
        $gateway = $this->gateway(['auth' => ['api_key' => 'old-name']]);

        $this->assertContains('Authorization: Bearer old-name', $this->headersOf($gateway));
    }

    public function test_a_missing_token_is_a_clear_error(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('TNZ_AUTH_TOKEN');

        $this->headersOf($this->gateway(['auth' => []]));
    }

    public function test_the_shipped_config_points_at_v3(): void
    {
        $this->assertSame(
            'https://api.tnz.co.nz/api/v3.00/sms',
            config('swissecho.routes_options.sms.gateway_options.tnz.url')
        );
    }
}
