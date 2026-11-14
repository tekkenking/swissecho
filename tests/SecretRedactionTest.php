<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * insight() is dispatched as AfterSend::$insightPayload and the README tells
 * people to log it, so the gateway credentials in the outbound request must
 * not travel with it.
 */
class SecretRedactionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    private function subject(): object
    {
        return new class {
            use SwissechoGatewayTrait;

            public function mask(mixed $payload): mixed
            {
                return $this->redactSecrets($payload);
            }
        };
    }

    public function test_it_masks_an_api_key_in_an_array_payload(): void
    {
        //The array shape: Kudisms and the voice gateways. Termii SMS and
        //Montnets build JSON instead - covered by JsonSecretRedactionTest,
        //because this comment once claimed Termii and the JSON path leaked.
        $subject = $this->subject();
        $masked = $subject->mask([
            'api_key'      => 'TLliveSecretValue',
            'phone_number' => '2348012345678',
            'code'         => '012345',
        ]);

        $this->assertSame($subject::REDACTED, $masked['api_key']);
        $this->assertSame('2348012345678', $masked['phone_number']);
        $this->assertSame('012345', $masked['code']);
    }

    public function test_it_masks_credentials_in_a_url_payload(): void
    {
        //The shape Nigerianbulksms and RouteMobile build.
        $subject = $this->subject();
        $masked = $subject->mask(
            'https://portal.nigeriabulksms.com/api/?username=acme&password=s3cret&message=Hi+there&mobiles=2348012345678'
        );

        $this->assertStringNotContainsString('s3cret', $masked);
        $this->assertStringContainsString('password='.$subject::REDACTED, $masked);

        //Everything that is not a credential must survive intact.
        $this->assertStringContainsString('username=acme', $masked);
        $this->assertStringContainsString('message=Hi+there', $masked);
        $this->assertStringContainsString('mobiles=2348012345678', $masked);
    }

    public function test_it_masks_nested_auth_blocks(): void
    {
        $subject = $this->subject();
        $masked = $subject->mask([
            'url'  => 'https://example.test/send',
            'auth' => ['api_key' => 'live_key', 'secret' => 'shhh'],
        ]);

        $this->assertSame($subject::REDACTED, $masked['auth']);
        $this->assertSame('https://example.test/send', $masked['url']);
    }

    public function test_it_leaves_a_human_readable_message_untouched(): void
    {
        //A body like this contains '=' but is not a query string, and
        //rewriting it would corrupt the record of what was sent.
        $body = 'Your total = 50% off, token of our thanks';

        $this->assertSame($body, $this->subject()->mask($body));
    }

    public function test_redaction_can_be_turned_off(): void
    {
        config(['swissecho.redact_secrets' => false]);

        $subject = new class {
            use SwissechoGatewayTrait;

            public function __construct()
            {
                $this->requestPayload = ['api_key' => 'live_key'];
            }
        };

        $this->assertSame('live_key', $subject->insight()['request']['api_key']);
    }
}
