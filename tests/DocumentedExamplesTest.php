<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Runs the README's own send examples verbatim.
 *
 * Every one of them raised 'No place could be determined' on a fresh
 * install, because the quick start set no place and the env block called
 * SWISSECHO_DEFAULT_PLACE optional. Examples that do not run are worse
 * than no examples, so they are pinned here.
 */
class DocumentedExamplesTest extends TestCase
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

        //Exactly what the quick start now tells you to set, and nothing more.
        $app['config']->set('swissecho.routes_options.sms.default_place', 'nga');
    }

    public function test_the_quick_start_one_liner(): void
    {
        (new Swissecho())->quick('2348012345678', 'Your OTP code is 1234');

        $this->assertTrue(true, 'quick() must work with only a default place set');
    }

    public function test_the_quick_start_fluent_example(): void
    {
        (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('2348012345678')
                      ->content('Your order has been shipped!');
        })->gateway('termii')->sender('MyApp')->go();

        $this->assertTrue(true);
    }

    public function test_the_full_control_example(): void
    {
        (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('2348012345678, 2348098765432')
                      ->content('Your order has been shipped!')
                      ->line('Track it at https://example.com/track');
        })->to('2348011111111')->sender('MyApp')->gateway('termii')->go();

        $this->assertTrue(true);
    }

    public function test_the_property_based_example(): void
    {
        $sw = new Swissecho();
        $sw->gateway('termii');
        $sw->to = '2348012345678';
        $sw->sender = 'MyApp';
        $sw->message = 'The world is a beautiful place created by GOD';
        $sw->go();

        $this->assertTrue(true);
    }

    public function test_the_gateway_override_example(): void
    {
        (new Swissecho())->gateway('routemobile')->quick('2348012345678', 'Your OTP code is 1234');

        $this->assertTrue(true);
    }

    public function test_the_quick_start_reads_the_result(): void
    {
        $result = (new Swissecho())->quick('2348012345678', 'Your OTP code is 1234');

        $this->assertTrue($result->success);
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }

    public function test_the_phone_number_formats_shown_all_reach_the_same_number(): void
    {
        foreach (['08012345678', '2348012345678', '+234 801 234 5678', '(0801) 234-5678'] as $written) {
            $result = (new Swissecho())->route('sms', function ($ms) use ($written) {
                return $ms->to($written)->content('hello');
            })->go();

            $this->assertSame(['2348012345678'], $result->to, $written);
        }
    }

    public function test_a_foreign_number_written_with_a_plus_is_sent_as_given(): void
    {
        $result = (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('+447911123456')->content('hello');
        })->go();

        $this->assertSame(['447911123456'], $result->to);
    }

    public function test_the_several_requests_example(): void
    {
        config(['swissecho.routes_options.voice.default_place' => 'nga']);

        $result = (new Swissecho())->route('voice', function ($ms) {
            return $ms->to('08012345678,08087654321')->content('123456');
        })->go();

        $lines = [];

        foreach ($result->results as $one) {
            $lines[] = $one->to.($one->success ? ' accepted' : ' rejected: '.$one->errorMessage);
        }

        $this->assertSame(['2348012345678 accepted', '2348087654321 accepted'], $lines);
    }

    public function test_the_twilio_example(): void
    {
        config([
            'swissecho.routes_options.sms.gateway_options.twilio.account_sid' => 'ACxxxxxxxx',
            'swissecho.routes_options.sms.gateway_options.twilio.auth.token' => 'your_auth_token',
            'swissecho.routes_options.sms.gateway_options.twilio.sender' => '+15005550006',
        ]);

        $result = (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('+14155552671')->content('Your code is 1234');
        })->gateway('twilio')->go();

        $this->assertTrue($result->success);
        $this->assertSame('14155552671', $result->to);
    }

    public function test_the_vonage_example(): void
    {
        config([
            'swissecho.routes_options.sms.gateway_options.vonage.auth.api_key' => 'your_api_key',
            'swissecho.routes_options.sms.gateway_options.vonage.auth.api_secret' => 'your_api_secret',
            'swissecho.routes_options.sms.gateway_options.vonage.sender' => 'MyApp',
        ]);

        $result = (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('+447911123456')->content('Your code is 1234');
        })->gateway('vonage')->go();

        $this->assertTrue($result->success);
    }

    public function test_the_place_mapping_example_for_twilio_and_vonage(): void
    {
        config([
            'swissecho.routes_options.sms.places.usa' => ['gateway' => 'twilio', 'phonecode' => '1'],
            'swissecho.routes_options.sms.gateway_options.twilio.account_sid' => 'ACxxxxxxxx',
            'swissecho.routes_options.sms.gateway_options.twilio.auth.token' => 'your_auth_token',
            'swissecho.routes_options.sms.gateway_options.twilio.sender' => '+15005550006',
        ]);

        $result = (new Swissecho())->route('sms', function ($ms) {
            return $ms->to('(415) 555-2671')->content('hello')->place('usa');
        })->go();

        $this->assertSame('twilio', $result->gateway);
        $this->assertSame('14155552671', $result->to);
    }
}
