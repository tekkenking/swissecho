<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoMock;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class MockModeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('swissecho.live', false);
        $app['config']->set('swissecho.fake', 'log');
        //Deliberately not 'local': mock mode used to go silent anywhere else.
        $app['config']->set('app.env', 'staging');
    }

    private function message(): SwissechoMessage
    {
        return (new SwissechoMessage())->to('2348012345678')->sender('MyApp')->line('hello');
    }

    public function test_mock_mode_records_the_send_outside_the_local_environment(): void
    {
        $result = (new SwissechoMock())->mockSend('SomeGatewayClass', $this->message());

        //On master this returned [] on staging, so nothing was logged or
        //mailed while execCurl() still reported the send as successful.
        $this->assertNotSame([], $result);
        $this->assertTrue($result['status']);
        $this->assertStringContainsString('hello', $result['response']['message']);
        $this->assertStringContainsString('2348012345678', $result['response']['message']);
    }

    public function test_an_unknown_mock_driver_throws_instead_of_a_fatal_error(): void
    {
        config(['swissecho.fake' => 'logs']);

        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('unknown mock driver "logs"');

        (new SwissechoMock())->mockSend('SomeGatewayClass', $this->message());
    }

    public function test_mailing_the_mock_without_an_address_throws(): void
    {
        config(['swissecho.fake' => 'mail', 'swissecho.fake_mail' => null]);

        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('SWISSECHO_FAKE_MAIL');

        (new SwissechoMock())->mockSend('SomeGatewayClass', $this->message());
    }

    public function test_package_exceptions_are_left_to_laravel_to_report(): void
    {
        //Returning false means Laravel logs it at error level with a trace.
        //The old report() logged at debug and returned void, which told the
        //handler it was done - so at LOG_LEVEL=error nothing was recorded.
        $this->assertFalse((new SwissechoException('boom'))->report());
    }
}
