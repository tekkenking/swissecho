<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\Routes\Slack\SlackRoute;
use Tekkenking\Swissecho\Routes\Sms\SmsRoute;
use Tekkenking\Swissecho\Routes\Telegram\TelegramRoute;
use Tekkenking\Swissecho\Routes\Voice\VoiceRoute;
use Tekkenking\Swissecho\Routes\Whatsapp\WhatsappRoute;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class RouteContractTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    public static function routeClasses(): array
    {
        return [
            'sms'      => [SmsRoute::class],
            'voice'    => [VoiceRoute::class],
            'whatsapp' => [WhatsappRoute::class],
            'slack'    => [SlackRoute::class],
            'telegram' => [TelegramRoute::class],
        ];
    }

    /**
     * BaseRoute calls prepareSender() from msgBuilderInitForSendViaNotification()
     * and msgBuilderInitForDirectSend() without declaring it. Only three of the
     * five route classes defined it, so Slack and Telegram would have failed
     * with 'Call to undefined method' had either inherited method ever been
     * used from them - both are protected and reachable.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('routeClasses')]
    public function test_every_route_can_resolve_a_sender(string $routeClass): void
    {
        $this->assertTrue(
            method_exists($routeClass, 'prepareSender'),
            $routeClass . ' must be able to resolve a sender, even if the answer is null'
        );
    }

    public function test_the_base_route_supplies_the_default(): void
    {
        $this->assertTrue(method_exists(BaseRoute::class, 'prepareSender'));
    }

    public function test_the_removed_interface_is_gone(): void
    {
        //BaseRouteInterface was implemented by nothing and its declared
        //methods did not match any route class.
        $this->assertFalse(interface_exists('Tekkenking\Swissecho\Routes\BaseRouteInterface'));
    }
}
