<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Twilio\Twilio;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Vonage\Vonage;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class TwilioAndVonageTest extends TestCase
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

        $twilio = 'swissecho.routes_options.sms.gateway_options.twilio.';
        $app['config']->set($twilio.'account_sid', 'ACtest');
        $app['config']->set($twilio.'auth.token', 'twilio-token');
        $app['config']->set($twilio.'sender', '+15005550006');

        $vonage = 'swissecho.routes_options.sms.gateway_options.vonage.';
        $app['config']->set($vonage.'auth.api_key', 'vonage-key');
        $app['config']->set($vonage.'auth.api_secret', 'vonage-secret');
        $app['config']->set($vonage.'sender', 'MyApp');
    }

    private function build(string $class, array $config, ?string $sender = null): array
    {
        $message = (new SwissechoMessage())->to(['2348012345678'])->line('hello');

        if ($sender !== null) {
            $message->sender($sender);
        }

        $gateway = new $class($config, $message);

        $current = new \ReflectionProperty($gateway, 'currentRecipient');
        $current->setValue($gateway, '2348012345678');

        return $gateway->init();
    }

    private function invokeOn(string $class, array $config, string $method): mixed
    {
        $gateway = new $class($config, (new SwissechoMessage())->to(['2348012345678'])->line('hello'));

        return (new \ReflectionMethod($gateway, $method))->invoke($gateway);
    }

    public function test_twilio_builds_the_documented_request(): void
    {
        $payload = $this->build(Twilio::class, ['account_sid' => 'ACtest', 'auth' => ['token' => 't']], '+15005550006');

        //E.164 with the plus, and a phone-number sender left whole.
        $this->assertSame(['To' => '+2348012345678', 'Body' => 'hello', 'From' => '+15005550006'], $payload);
    }

    public function test_twilio_prefers_a_messaging_service(): void
    {
        //The sender here is only the gateway's configured default, so the
        //Messaging Service stands in for it.
        $payload = $this->build(Twilio::class, [
            'account_sid' => 'ACtest', 'auth' => ['token' => 't'],
            'sender' => '+15005550006', 'messaging_service_sid' => 'MGtest',
        ], '+15005550006');

        $this->assertSame('MGtest', $payload['MessagingServiceSid']);
        $this->assertArrayNotHasKey('From', $payload);
    }

    public function test_a_sender_chosen_for_the_message_beats_the_messaging_service(): void
    {
        $payload = $this->build(Twilio::class, [
            'account_sid' => 'ACtest', 'auth' => ['token' => 't'],
            'sender' => '+15005550006', 'messaging_service_sid' => 'MGtest',
        ], 'PartyShop');

        $this->assertSame('PartyShop', $payload['From']);
        $this->assertArrayNotHasKey('MessagingServiceSid', $payload);
    }

    public function test_twilio_falls_back_to_the_message_sender(): void
    {
        $payload = $this->build(Twilio::class, ['account_sid' => 'ACtest', 'auth' => ['token' => 't']], 'MyBrand');

        $this->assertSame('MyBrand', $payload['From']);
    }

    public function test_twilio_with_no_sender_at_all_says_so(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('TWILIO_FROM or TWILIO_MESSAGING_SERVICE_SID');

        $this->build(Twilio::class, ['account_sid' => 'ACtest', 'auth' => ['token' => 't']]);
    }

    public function test_twilio_builds_the_account_endpoint(): void
    {
        $this->assertSame(
            'https://api.twilio.com/2010-04-01/Accounts/ACtest/Messages.json',
            $this->invokeOn(Twilio::class, ['account_sid' => 'ACtest'], 'endpoint')
        );
    }

    public function test_twilio_without_an_account_sid_says_so(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('TWILIO_ACCOUNT_SID');

        $this->invokeOn(Twilio::class, [], 'endpoint');
    }

    public function test_vonage_builds_the_documented_request(): void
    {
        $payload = $this->build(Vonage::class, ['auth' => ['api_key' => 'k', 'api_secret' => 's']], 'MyApp');

        $this->assertSame(
            ['api_key' => 'k', 'api_secret' => 's', 'to' => '2348012345678', 'from' => 'MyApp', 'text' => 'hello'],
            $payload
        );
    }

    public function test_vonage_without_credentials_says_so(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('VONAGE_API_KEY');

        $this->build(Vonage::class, [], 'MyApp');
    }

    public static function replies(): array
    {
        return [
            'twilio accepted' => [Twilio::class, '{"sid":"SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","status":"queued","error_code":null,"error_message":null}', 201, true, 'SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', null, null],
            'twilio rejected' => [Twilio::class, '{"status":400,"message":"No to number is specified","code":21201,"more_info":"http://www.twilio.com/docs/errors/21201"}', 400, false, null, '21201', 'No to number is specified'],
            'vonage accepted' => [Vonage::class, '{"message-count":"1","messages":[{"to":"2348012345678","message-id":"aaaaaaaa-bbbb-cccc-dddd-0123456789ab","status":"0"}]}', 200, true, 'aaaaaaaa-bbbb-cccc-dddd-0123456789ab', null, null],
            //HTTP 200, and still a rejection.
            'vonage rejected inside a 200' => [Vonage::class, '{"message-count":"1","messages":[{"status":"2","error-text":"Missing to param"}]}', 200, false, null, '2', 'Missing to param'],
            'vonage unrecognised body falls back to http' => [Vonage::class, 'Bad Gateway', 502, false, null, '502', 'Gateway rejected the message with HTTP 502'],
        ];
    }

    #[DataProvider('replies')]
    public function test_it_reads_the_providers_reply(
        string $class, string $body, int $http, bool $success, ?string $messageId, ?string $errorCode, ?string $errorMessage
    ): void {
        $gateway = new $class([], (new SwissechoMessage())->to(['2348012345678'])->line('hello'));
        $verdict = (new \ReflectionMethod($gateway, 'interpretResponse'))->invoke($gateway, $body, $http);

        $this->assertSame($success, $verdict['success']);
        $this->assertSame($messageId, $verdict['message_id'] ?? null);
        $this->assertSame($errorCode, $verdict['error_code'] ?? null);
        $this->assertSame($errorMessage, $verdict['error_message'] ?? null);
    }

    public static function gateways(): array
    {
        return ['twilio' => ['twilio'], 'vonage' => ['vonage']];
    }

    #[DataProvider('gateways')]
    public function test_it_sends_one_request_per_recipient(string $gateway): void
    {
        $events = [];
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$events) {
            $events[] = $event;
        });

        $result = (new Swissecho())->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('08012345678,08087654321')->content('hello');
        })->gateway($gateway)->go();

        $this->assertTrue($result->success);
        $this->assertSame($gateway, $result->gateway);
        $this->assertSame(['2348012345678', '2348087654321'], array_map(fn ($e) => $e->formattedResponse['to'], $events));
    }

    public function test_vonage_credentials_are_masked_in_the_event(): void
    {
        $captured = null;
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event->insightPayload['request'];
        });

        (new Swissecho())->route('sms', fn (SwissechoMessage $ms) => $ms->to('08012345678')->content('hello'))
            ->gateway('vonage')->go();

        $this->assertSame('[redacted]', $captured['api_key']);
        $this->assertSame('[redacted]', $captured['api_secret']);
        $this->assertSame('2348012345678', $captured['to']);
    }

    public function test_twilio_puts_no_credential_in_the_payload(): void
    {
        $captured = null;
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event->insightPayload['request'];
        });

        (new Swissecho())->route('sms', fn (SwissechoMessage $ms) => $ms->to('08012345678')->content('hello'))
            ->gateway('twilio')->go();

        //The SID and token travel as HTTP Basic, never in the body.
        $this->assertSame(['To', 'Body', 'From'], array_keys($captured));
    }
}
