<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes;

use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\SwissechoMock;

/**
 * Shared HTTP + event-dispatch logic for HTTP-based notification channels
 * such as Slack, Telegram, Discord, Microsoft Teams, etc.
 *
 * Classes using this trait must define:
 *  - $config (array)       — loaded swissecho config
 *  - $msgBuilder           — SwissechoMessage instance
 *  - $requestPayload (array) — the outgoing payload (populated before dispatchAfterSendEvent)
 */
trait HttpRoute
{
    /**
     * Perform a JSON POST request via cURL.
     *
     * @param  string  $url
     * @param  array   $payload
     * @param  array   $headers  e.g. ['Content-Type: application/json', 'Authorization: ******']
     * @return array
     */
    protected function postJson(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $output = curl_exec($ch);
        $error  = curl_error($ch);
        $errno  = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            return ['error' => true, 'message' => $error];
        }

        return json_decode((string) $output, true) ?? ['raw' => $output];
    }

    /**
     * Handle mock/non-live mode: log the send via SwissechoMock and dispatch the event.
     */
    protected function handleHttpMock(): void
    {
        $mock   = new SwissechoMock();
        $result = $mock->mockSend(static::class, $this->msgBuilder);
        $this->dispatchAfterSendEvent([
            'mocked'           => true,
            'gateway_response' => $result['response'] ?? [],
        ]);
    }

    /**
     * Dispatch the AfterSend event with a normalised response envelope.
     *
     * @param  array  $gatewayResponse  Raw response from the HTTP call (or mock).
     */
    protected function dispatchAfterSendEvent(array $gatewayResponse): void
    {
        $hasError = (bool) ($gatewayResponse['error'] ?? false);
        $ok       = (bool) ($gatewayResponse['ok'] ?? true);
        $mocked   = (bool) ($gatewayResponse['mocked'] ?? false);

        $status = $mocked || (!$hasError && $ok);

        $to = $this->msgBuilder->to;

        $formattedResponse = [
            'status'           => $status,
            'partner_response' => $gatewayResponse,
            'from'             => $this->msgBuilder->sender ?? null,
            'to'               => $to,
            'body'             => $this->msgBuilder->message,
            'route'            => $this->msgBuilder->route,
            'gateway'          => $this->msgBuilder->gateway,
            'identifier'       => $this->msgBuilder->identifier ?? null,
            'timestamp'        => now()->toDateTimeString(),
        ];

        AfterSend::dispatch(
            ['request' => $this->requestPayload, 'response' => $gatewayResponse],
            $formattedResponse,
            $this->msgBuilder->identifier ?? null
        );
    }

    /**
     * Ensure the msgBuilder has `route` and `gateway` set so downstream
     * helpers and the event envelope are always populated.
     *
     * @param  string  $routeName   e.g. 'slack'
     * @param  string  $gatewayName e.g. 'slack'
     */
    protected function ensureHttpRouteAndGateway(string $routeName, string $gatewayName): void
    {
        if (empty($this->msgBuilder->route)) {
            $this->msgBuilder->route = $routeName;
        }
        if (empty($this->msgBuilder->gateway)) {
            $this->msgBuilder->gateway = $gatewayName;
        }
    }

    /**
     * Override BaseRoute::setPlace() so HTTP-only channels (Slack, Telegram…)
     * are never subjected to geo/phone-code logic.
     */
    protected function setPlace(): void
    {
        $this->place = 'none';
    }

    /**
     * Override BaseRoute::setPlaceConfig() — no place config needed for HTTP channels.
     */
    protected function setPlaceConfig(): void
    {
        $this->placeConfig = [];
    }
}
