<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * AfterSend::$identifier used to be the notifiable object itself, so the
 * whole model reached the log context of any listener that recorded the
 * event.
 */
class MessageIdentifierTest extends TestCase
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

    private function notification(): Notification
    {
        return new class extends Notification {
            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->sender('MyApp')->content('hello');
            }
        };
    }

    private function identifierFor(object $notifiable): mixed
    {
        $captured = null;

        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event->identifier;
        });

        (new Swissecho())->send($notifiable, $this->notification());

        return $captured;
    }

    public function test_an_eloquent_style_notifiable_is_reduced_to_class_and_key(): void
    {
        $notifiable = new class {
            public string $phone = '08012345678';
            public string $email = 'someone@example.test';
            public string $home_address = '12 Private Road';

            public function getKey()
            {
                return 42;
            }
        };

        $identifier = $this->identifierFor($notifiable);

        $this->assertIsString($identifier);
        $this->assertStringEndsWith(':42', $identifier);

        //None of the model's own data may travel with the event.
        $this->assertStringNotContainsString('someone@example.test', $identifier);
        $this->assertStringNotContainsString('12 Private Road', $identifier);
    }

    public function test_the_notifiable_itself_is_no_longer_the_identifier(): void
    {
        $notifiable = new class {
            public string $phone = '08012345678';

            public function getKey()
            {
                return 7;
            }
        };

        $this->assertIsNotObject($this->identifierFor($notifiable));
    }

    public function test_a_notifiable_can_choose_its_own_identifier(): void
    {
        $notifiable = new class {
            public string $phone = '08012345678';

            public function routeNotificationIdentifier()
            {
                return 'order-9001';
            }
        };

        $this->assertSame('order-9001', $this->identifierFor($notifiable));
    }

    public function test_an_id_property_is_used_when_there_is_no_key_method(): void
    {
        $notifiable = new class {
            public string $phone = '08012345678';
            public int $id = 13;
        };

        $this->assertStringEndsWith(':13', $this->identifierFor($notifiable));
    }

    public function test_a_notifiable_with_nothing_identifying_carries_no_identifier(): void
    {
        $notifiable = new class {
            public string $phone = '08012345678';
        };

        $this->assertNull($this->identifierFor($notifiable));
    }
}
