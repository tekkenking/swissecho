<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes;

interface BaseRouteInterface
{
    public function mockByLog($buildMock, $gatewayConfig, $msgBuilder);

    public function mockByMail($buildMock, $gatewayConfig, $msgBuilder);

    public function mockSend($gatewayConfig, $msgBuilder);
}
