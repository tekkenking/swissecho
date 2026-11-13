<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Five gateways disabled TLS peer verification in their own send(), and
 * none but the Termii voice gateway set any timeout. These options are now
 * applied centrally, after the gateway returns its handle, so they win.
 */
class TransportDefaultsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    private function curlOptions(): array
    {
        $subject = new class {
            use SwissechoGatewayTrait;

            public function options(): array
            {
                return $this->transportOptions();
            }
        };

        return $subject->options();
    }

    public function test_tls_is_verified_by_default(): void
    {
        $options = $this->curlOptions();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function test_a_timeout_is_always_set_by_default(): void
    {
        $options = $this->curlOptions();

        //Without this a hung provider held a PHP worker open indefinitely.
        $this->assertSame(30, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(10, $options[CURLOPT_CONNECTTIMEOUT]);
    }

    public function test_verification_can_be_turned_off_for_a_broken_provider(): void
    {
        config(['swissecho.transport.verify_tls' => false]);

        $options = $this->curlOptions();

        $this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function test_timeouts_are_configurable(): void
    {
        config(['swissecho.transport.timeout' => 5, 'swissecho.transport.connect_timeout' => 2]);

        $options = $this->curlOptions();

        $this->assertSame(5, $options[CURLOPT_TIMEOUT]);
        $this->assertSame(2, $options[CURLOPT_CONNECTTIMEOUT]);
    }

    public function test_a_zero_timeout_is_left_off_the_handle(): void
    {
        //0 is cURL's own 'wait forever', so setting it would be worse than
        //leaving whatever the gateway chose.
        config(['swissecho.transport.timeout' => 0, 'swissecho.transport.connect_timeout' => 0]);

        $options = $this->curlOptions();

        $this->assertArrayNotHasKey(CURLOPT_TIMEOUT, $options);
        $this->assertArrayNotHasKey(CURLOPT_CONNECTTIMEOUT, $options);
    }

    public function test_no_gateway_disables_verification_any_more(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../src')
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $body = file_get_contents($file->getPathname());

                if (preg_match('/CURLOPT_SSL_VERIFYPEER,\s*(false|FALSE)/', $body)) {
                    $offenders[] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
