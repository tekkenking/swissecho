<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * The foundation of the package, as one scenario.
 *
 * A platform - an inventory product, say - sends on behalf of the
 * businesses that use it. It has its own default sender, but a message to a
 * business's customer must carry that business's name, not the platform's.
 * And the gateway must follow the customer's country: one provider for
 * Nigeria, another for the United States, another for the United Kingdom.
 */
class DynamicSenderAndGatewayTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $sms = 'swissecho.routes_options.sms.';

        $config->set('swissecho.live', false);
        $config->set('swissecho.fake', 'log');
        $config->set('app.env', 'local');

        //The platform's own name: the default, never the last word.
        $config->set('swissecho.sender', 'PlatformCo');

        $config->set($sms.'default_place', 'nga');
        $config->set($sms.'places.nga', ['gateway' => 'termii', 'phonecode' => '234']);
        $config->set($sms.'places.usa', ['gateway' => 'twilio', 'phonecode' => '1']);
        $config->set($sms.'places.gbr', ['gateway' => 'vonage', 'phonecode' => '44']);

        $config->set($sms.'gateway_options.twilio.account_sid', 'ACtest');
        $config->set($sms.'gateway_options.twilio.auth.token', 'token');
        $config->set($sms.'gateway_options.twilio.sender', '+15005550006');
        $config->set($sms.'gateway_options.vonage.auth.api_key', 'key');
        $config->set($sms.'gateway_options.vonage.auth.api_secret', 'secret');
        $config->set($sms.'gateway_options.vonage.sender', 'VonageDflt');
    }

    /**
     * @return array<int, array{gateway: string, reported: mixed, sent: mixed, to: mixed}>
     */
    private function capture(callable $send): array
    {
        $seen = [];

        Event::listen(AfterSend::class, function (AfterSend $event) use (&$seen) {
            $request = $event->insightPayload['request'];
            $request = is_string($request) ? (json_decode($request, true) ?? []) : $request;

            $seen[] = [
                'gateway'  => $event->formattedResponse['gateway'],
                'reported' => $event->formattedResponse['from'],
                //What actually went into the provider's request.
                'sent'     => $request['From'] ?? $request['from'] ?? null,
                'to'       => $event->formattedResponse['to'],
            ];
        });

        $send();

        return $seen;
    }

    private function customer(string $phone, string $place, ?string $business = 'PartyShop'): object
    {
        return new class($phone, $place, $business) {
            public function __construct(public string $phone, private string $place, private ?string $business)
            {
            }

            public function routeNotificationPlace(): string
            {
                return $this->place;
            }

            public function routeNotificationSmsSender(): ?string
            {
                return $this->business;
            }
        };
    }

    private function orderReady(): Notification
    {
        return new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('Your order is ready');
            }
        };
    }

    public static function countries(): array
    {
        return [
            'nigeria goes through termii'        => ['08012345678', 'nga', 'termii'],
            'united states goes through twilio'  => ['4155552671', 'usa', 'twilio'],
            'united kingdom goes through vonage' => ['07911123456', 'gbr', 'vonage'],
        ];
    }

    #[DataProvider('countries')]
    public function test_the_customers_country_chooses_the_gateway(string $phone, string $place, string $gateway): void
    {
        $seen = $this->capture(fn () => (new Swissecho())->send($this->customer($phone, $place), $this->orderReady()));

        $this->assertSame($gateway, $seen[0]['gateway']);
    }

    #[DataProvider('countries')]
    public function test_the_business_name_replaces_the_platform_default(string $phone, string $place, string $gateway): void
    {
        $seen = $this->capture(fn () => (new Swissecho())->send($this->customer($phone, $place), $this->orderReady()));

        //In the provider's request, not only in what the event reports.
        //Twilio and Vonage once sent their configured default here while
        //the event still claimed the business name.
        $this->assertSame('PartyShop', $seen[0]['sent'], $gateway.' must send the business sender');
        $this->assertSame('PartyShop', $seen[0]['reported']);
    }

    #[DataProvider('countries')]
    public function test_a_sender_on_the_message_wins_on_a_direct_send(string $phone, string $place, string $gateway): void
    {
        $seen = $this->capture(function () use ($phone, $place) {
            (new Swissecho())->route('sms', function (SwissechoMessage $ms) use ($phone, $place) {
                return $ms->to($phone)->content('hello')->place($place)->sender('PartyShop');
            })->go();
        });

        $this->assertSame($gateway, $seen[0]['gateway']);
        $this->assertSame('PartyShop', $seen[0]['sent']);
    }

    public function test_a_sender_on_the_message_beats_the_notifiables_own(): void
    {
        $notification = new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('hello')->sender('Campaign');
            }
        };

        $seen = $this->capture(fn () => (new Swissecho())->send($this->customer('08012345678', 'nga'), $notification));

        $this->assertSame('Campaign', $seen[0]['sent']);
    }

    public static function defaults(): array
    {
        return [
            //The gateway's own default sits between the dynamic sender and
            //the platform's.
            'twilio falls back to its own number' => ['4155552671', 'usa', '+15005550006'],
            'vonage falls back to its own sender' => ['07911123456', 'gbr', 'VonageDflt'],
            'termii falls back to the platform'   => ['08012345678', 'nga', 'PlatformCo'],
        ];
    }

    #[DataProvider('defaults')]
    public function test_with_nothing_dynamic_the_configured_default_is_used(string $phone, string $place, string $expected): void
    {
        $seen = $this->capture(
            fn () => (new Swissecho())->send($this->customer($phone, $place, null), $this->orderReady())
        );

        $this->assertSame($expected, $seen[0]['sent']);
    }

    public function test_a_twilio_messaging_service_yields_to_a_dynamic_sender(): void
    {
        config(['swissecho.routes_options.sms.gateway_options.twilio.messaging_service_sid' => 'MGtest']);

        $withBusiness = $this->capture(
            fn () => (new Swissecho())->send($this->customer('4155552671', 'usa'), $this->orderReady())
        );

        $this->assertSame('PartyShop', $withBusiness[0]['sent']);
    }

    public function test_one_notification_reaches_three_countries_through_three_gateways(): void
    {
        $seen = $this->capture(function () {
            foreach ([['08012345678', 'nga'], ['4155552671', 'usa'], ['07911123456', 'gbr']] as [$phone, $place]) {
                (new Swissecho())->send($this->customer($phone, $place), $this->orderReady());
            }
        });

        $this->assertSame(['termii', 'twilio', 'vonage'], array_column($seen, 'gateway'));
        $this->assertSame(['PartyShop', 'PartyShop', 'PartyShop'], array_column($seen, 'sent'));
        $this->assertSame([['2348012345678'], '14155552671', '447911123456'], array_column($seen, 'to'));
    }
}
