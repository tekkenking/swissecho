<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class SenderAndRecipientTest extends TestCase
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

        //Two gateways with different sender IDs, so we can see which one wins.
        $app['config']->set('swissecho.routes_options.sms.gateway_options.termii.sender', 'TermiiID');
        $app['config']->set('swissecho.routes_options.sms.gateway_options.wirepick.sender', 'WirepickID');
    }

    private function sendSms(callable $build): array
    {
        $captured = [];

        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event->formattedResponse;
        });

        (new Swissecho())->route('sms', $build)->go();

        return $captured;
    }

    public function test_the_sender_comes_from_the_gateway_the_place_chose(): void
    {
        //'gha' is configured for wirepick; termii is the route default.
        $response = $this->sendSms(function (SwissechoMessage $ms) {
            return $ms->to('233501234567')->content('hello')->place('gha');
        });

        //Resolving the sender before setGatewayFromPlace() gave 'TermiiID'
        //here, so the message went through Wirepick wearing Termii's name.
        $this->assertSame('WirepickID', $response['from']);
    }

    public function test_a_trailing_comma_does_not_add_the_bare_country_code(): void
    {
        $response = $this->sendSms(function (SwissechoMessage $ms) {
            return $ms->to('08012345678, ')->content('hello')->place('nga');
        });

        //Previously this was ['2348012345678', '234'] - a second billed
        //message addressed to the country code on its own.
        $this->assertSame(['2348012345678'], $response['to']);
    }

    public function test_an_international_number_survives_a_foreign_place(): void
    {
        $response = $this->sendSms(function (SwissechoMessage $ms) {
            //A UK number sent while the place is Nigeria.
            return $ms->to('+447911123456')->content('hello')->place('nga');
        });

        //This used to be ['234447911123456'] - billed, reported as a success,
        //and impossible to deliver.
        $this->assertSame(['447911123456'], $response['to']);
    }

    public function test_a_place_without_a_phonecode_is_reported(): void
    {
        config(['swissecho.routes_options.sms.places.usa' => ['gateway' => 'termii']]);

        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('has no phonecode configured');

        $this->sendSms(function (SwissechoMessage $ms) {
            return $ms->to('02125551234')->content('hello')->place('usa');
        });
    }

    public function test_a_notifiable_returning_no_place_falls_back_to_the_default(): void
    {
        config(['swissecho.routes_options.sms.default_place' => 'nga']);

        $notifiable = new class {
            public string $phone = '08012345678';

            public function routeNotificationPlace()
            {
                //A nullable country column with nothing in it.
                return null;
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('hello');
            }
        };

        $captured = [];
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event->formattedResponse;
        });

        //This used to be a TypeError from strtolower(null), even though
        //default_place was configured.
        (new Swissecho())->send($notifiable, $notification);

        $this->assertSame(['2348012345678'], $captured['to']);
    }
}
