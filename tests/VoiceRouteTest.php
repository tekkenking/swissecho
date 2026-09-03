<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class VoiceRouteTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Use mock/non-live mode so no real HTTP calls are made
        $app['config']->set('swissecho.live', false);
        $app['config']->set('swissecho.fake', 'log');
        $app['config']->set('app.env', 'local');
        $app['config']->set('swissecho.routes_options.voice.default_place', 'nga');
    }

    public function test_voice_route_class_exists(): void
    {
        $this->assertTrue(class_exists(\Tekkenking\Swissecho\Routes\Voice\VoiceRoute::class));
    }

    public function test_swissecho_vias_includes_voice(): void
    {
        $swissecho = new \Tekkenking\Swissecho\Swissecho();
        $reflection = new \ReflectionClass($swissecho);
        $prop = $reflection->getProperty('vias');
        $prop->setAccessible(true);
        $this->assertContains('voice', $prop->getValue($swissecho));
    }

    public function test_voice_route_is_discoverable(): void
    {
        $swissecho = new \Tekkenking\Swissecho\Swissecho();
        $reflection = new \ReflectionClass($swissecho);

        $prop = $reflection->getProperty('route');
        $prop->setAccessible(true);
        $prop->setValue($swissecho, 'Voice');

        $method = $reflection->getMethod('isRouteAvailable');
        $method->setAccessible(true);

        $result = $method->invoke($swissecho);
        $this->assertSame(\Tekkenking\Swissecho\Routes\Voice\VoiceRoute::class, $result);
    }

    public function test_voice_route_send_via_notification_dispatches_mock(): void
    {
        $notifiable = new class {
            public string $phone = '08012345678';
        };

        $notification = new class extends Notification {
            public function toVoice($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->line('123456');
            }
        };

        $swissecho = new \Tekkenking\Swissecho\Swissecho();

        // Should not throw; mock mode prevents real HTTP calls
        $swissecho->send($notifiable, $notification);
        $this->assertTrue(true);
    }
}
