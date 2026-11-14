<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes;

use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\SendResult;
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
     * Whether the recipient the caller asked for was actually used.
     *
     * A boolean rather than a nullable "reported recipient", because a
     * deliberate null is indistinguishable from unset through ??. A route
     * sets this false when it could not honour the recipient it was given,
     * so the AfterSend event does not claim a destination that was never
     * sent.
     *
     * @var bool
     */
    protected bool $reportsRequestedRecipient = true;

    /**
     * The provider's reply exactly as received, for SendResult::$providerRes.
     * Null in mock mode and when the request never got an answer.
     *
     * @var mixed
     */
    protected mixed $rawResponse = null;

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

        $this->applyTransportOptions($ch);

        $output   = curl_exec($ch);
        $error    = curl_error($ch);
        $errno    = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->rawResponse = $output === false ? null : $output;

        if ($errno) {
            return ['error' => true, 'message' => $error, 'http_code' => $httpCode];
        }

        return $this->buildEnvelope((string) $output, $httpCode);
    }

    /**
     * Apply the shared TLS and timeout settings to the handle.
     *
     * @param  \CurlHandle  $ch
     * @return void
     */
    protected function applyTransportOptions(\CurlHandle $ch): void
    {
        $transport      = config('swissecho.transport', []);
        $verify         = (bool) ($transport['verify_tls'] ?? true);
        $timeout        = (int) ($transport['timeout'] ?? 30);
        $connectTimeout = (int) ($transport['connect_timeout'] ?? 10);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout > 0 ? $timeout : 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout > 0 ? $connectTimeout : 10);
    }

    /**
     * Turn the raw body and status into the response envelope.
     *
     * @param  string  $output
     * @param  int  $httpCode
     * @return array
     */
    protected function buildEnvelope(string $output, int $httpCode): array
    {
        //is_array, not ??: a scalar JSON body such as 404 or "ok" decodes to
        //an int or a string, which would be returned from a method declared
        //: array and fail under strict_types.
        $decoded = json_decode($output, true);
        $body    = is_array($decoded) ? $decoded : ['raw' => $output];

        //Without this, a revoked Slack webhook replying 403 invalid_token
        //became ['raw' => 'invalid_token'] and was reported as a success.
        if ($httpCode >= 400) {
            return [
                'error'     => true,
                'message'   => 'Gateway rejected the message with HTTP '.$httpCode,
                'http_code' => $httpCode,
                'response'  => $body,
            ];
        }

        return $body + ['http_code' => $httpCode];
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

        //A route may know the recipient was not actually used - Slack's
        //webhook mode cannot send a channel - and must not report one that
        //never left the process.
        $to = $this->reportsRequestedRecipient ? $this->msgBuilder->to : null;

        $this->lastResult = $this->buildResult($gatewayResponse, $status, $mocked, $to);

        AfterSend::dispatch(
            ['request' => $this->requestPayload, 'response' => $gatewayResponse],
            $this->formatHttpResponse($gatewayResponse, $to),
            $this->msgBuilder->identifier ?? null,
            $this->lastResult
        );
    }

    /**
     * The formattedResponse array for the AfterSend event.
     *
     * @param  array  $gatewayResponse
     * @param  mixed  $to
     * @return array
     */
    protected function formatHttpResponse(array $gatewayResponse, mixed $to): array
    {
        return [
            'status'           => $this->lastResult->success,
            'partner_response' => $gatewayResponse,
            //Always null: neither Slack nor Telegram has a sender field in
            //its payload, so ->from() is accepted and never sent. Reporting
            //it made the event claim an identity that did not leave here.
            'from'             => null,
            'to'               => $to,
            'body'             => $this->msgBuilder->message,
            'route'            => $this->msgBuilder->route,
            'gateway'          => $this->msgBuilder->gateway,
            'identifier'       => $this->msgBuilder->identifier ?? null,
            'timestamp'        => now()->toDateTimeString(),
            //The same keys every gateway-backed route reports.
            'success'          => $this->lastResult->success,
            'message_id'       => $this->lastResult->messageId,
            'error_code'       => $this->lastResult->errorCode,
            'error_message'    => $this->lastResult->errorMessage,
            'provider_res'     => $this->lastResult->providerRes,
            'mocked'           => $this->lastResult->mocked,
        ];
    }

    /**
     * The send's outcome in the shape every route reports.
     *
     * @param  array  $gatewayResponse
     * @param  bool   $status
     * @param  bool   $mocked
     * @param  mixed  $to
     * @return SendResult
     */
    protected function buildResult(array $gatewayResponse, bool $status, bool $mocked, mixed $to): SendResult
    {
        [$errorCode, $errorMessage] = $status ? [null, null] : $this->errorFrom($gatewayResponse);

        return new SendResult(
            success: $status,
            messageId: $status && !$mocked ? $this->messageIdFrom($gatewayResponse) : null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            providerRes: $mocked ? null : $this->rawResponse,
            to: $to,
            route: $this->msgBuilder->route,
            gateway: $this->msgBuilder->gateway,
            mocked: $mocked,
        );
    }

    /**
     * Slack identifies a message by its `ts`, Telegram by `result.message_id`.
     * An incoming webhook returns no identifier at all.
     *
     * @param  array  $response
     * @return string|null
     */
    protected function messageIdFrom(array $response): ?string
    {
        $id = $response['ts'] ?? ($response['result']['message_id'] ?? null);

        return $id === null ? null : (string) $id;
    }

    /**
     * The provider's own reason for a failure, where it gave one.
     *
     * Slack's API puts a machine code in `error`; Telegram gives
     * `error_code` and `description`; a Slack webhook answers in plain text.
     * The envelope's own `error` key is a boolean, so only strings count.
     *
     * @param  array  $response
     * @return array{0: string|null, 1: string|null}
     */
    protected function errorFrom(array $response): array
    {
        $body = is_array($response['response'] ?? null) ? $response['response'] : $response;
        $slackError = is_string($body['error'] ?? null) ? $body['error'] : null;
        $plainText = is_string($body['raw'] ?? null) && $body['raw'] !== '' ? $body['raw'] : null;

        $code = $body['error_code'] ?? $slackError ?? ($response['http_code'] ?? null);
        $message = $body['description'] ?? $slackError ?? $plainText ?? ($response['message'] ?? null);

        return [
            $code === null ? null : (string) $code,
            $message === null ? null : (string) $message,
        ];
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
