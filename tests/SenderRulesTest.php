<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class SenderRulesTest extends TestCase
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

    private function send(string $gateway, ?string $sender = null)
    {
        return (new Swissecho())->route('sms', function (SwissechoMessage $ms) use ($sender) {
            $ms->to('08012345678')->content('hello');

            return $sender === null ? $ms : $ms->sender($sender);
        })->gateway($gateway)->go();
    }

    public function test_a_sender_is_sent_as_given(): void
    {
        //It used to be cut to 10 characters: one short of the 11 GSM allows.
        $this->assertSame('MyCompanyID', (new SwissechoMessage())->sender('MyCompanyID')->sender);

        //And a phone-number sender, which Twilio and WhatsApp use, was
        //mangled outright.
        $this->assertSame('+15005550006', (new SwissechoMessage())->sender('+15005550006')->sender);
    }

    public function test_a_gateway_that_needs_a_sender_refuses_to_send_without_one(): void
    {
        //Previously this went out with an empty sender, which the provider
        //rejects, after the request had already been made.
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('the termii gateway needs a sender ID');

        $this->send('termii');
    }

    public function test_the_global_sender_satisfies_it(): void
    {
        config(['swissecho.sender' => 'MyApp']);

        $this->assertTrue($this->send('termii')->success);
    }

    public function test_a_gateway_with_no_such_requirement_still_sends(): void
    {
        $this->assertTrue($this->send('montnets')->success);
    }

    public function test_a_sender_over_the_gateways_limit_is_refused_not_shortened(): void
    {
        config(['swissecho.routes_options.sms.gateway_options.termii.sender_max_length' => 11]);

        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('is 12 characters and the termii gateway allows 11');

        $this->send('termii', 'MyCompanyIDx');
    }

    public function test_a_sender_within_the_limit_passes(): void
    {
        config(['swissecho.routes_options.sms.gateway_options.termii.sender_max_length' => 11]);

        $this->assertTrue($this->send('termii', 'MyCompanyID')->success);
    }
}
