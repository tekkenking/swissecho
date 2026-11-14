<?php

namespace Tekkenking\Swissecho\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * A gateway send used to be judged only by curl_errno, so a provider
 * rejecting the message - a bad API key, no credit - was reported to the
 * caller as a successful send.
 */
class HttpFailureDetectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    private function subject(): object
    {
        return new class {
            use SwissechoGatewayTrait;

            public function failsOn(int $code): bool
            {
                return $this->isHttpFailure($code);
            }
        };
    }

    public static function codes(): array
    {
        return [
            //A non-HTTP handle reports 0, which must not count as a failure.
            'no status reported' => [0, false],
            'ok'                 => [200, false],
            'created'            => [201, false],
            'no content'         => [204, false],
            'redirect'           => [302, false],
            'bad request'        => [400, true],
            'unauthorized'       => [401, true],
            'forbidden'          => [403, true],
            'too many requests'  => [429, true],
            'server error'       => [500, true],
            'bad gateway'        => [502, true],
        ];
    }

    #[DataProvider('codes')]
    public function test_it_only_treats_4xx_and_5xx_as_a_failed_send(int $code, bool $expected): void
    {
        $this->assertSame($expected, $this->subject()->failsOn($code));
    }
}
