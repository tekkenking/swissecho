<?php

namespace Tekkenking\Swissecho;

use Illuminate\Support\Str;
use Tekkenking\Swissecho\Events\AfterSend;

trait SwissechoGatewayTrait
{

    /**
     * Placeholder written over any credential in the AfterSend payload.
     */
    public const REDACTED = '[redacted]';

    /**
     * @var mixed
     */
    public mixed $to;

    /**
     * @var string|mixed
     */
    public string $sender = '';

    /**
     * @var string
     */
    public string $body;

    /**
     * @var string|mixed
     */
    public mixed $identifier = null;

    /**
     * @var array
     */
    private array $payload;

    /**
     * @var array
     */
    public array $config;

    protected array $swissecho_config;

    private $responsePayload;

    /**
     * @var array
     */
    private $requestPayload;

    protected SwissechoMessage $msgBuilder;
    /**
     * @var array
     */
    private array $formattedResponse;

    /**
     * The outcome of the most recent request this gateway made.
     */
    protected ?SendResult $result = null;

    public function result(): ?SendResult
    {
        return $this->result;
    }

    /**
     * @return void
     */
    public function boot(): self
    {
        $this->swissecho_config = config('swissecho');
        $this->processDependencies();
        $this->requestPayload = $this->init();
        $ch = $this->send($this->requestPayload);
        $this->execCurl($ch ?? null);
        return $this;
    }

    /**
     * @return array
     */
    protected function checkDependencies(): array
    {
        return [];
    }

    /**
     * @return void
     * @throws SwissechoException
     */
    protected function processDependencies(): void
    {
        if(!method_exists($this, 'checkDependencies')) {
            return;
        }

        $dependenciesArr = $this->checkDependencies();
        foreach ($dependenciesArr ?? [] as $depArr) {
            $className = $depArr[0];
            $composerRequire = $depArr[1];

            if(!class_exists($className)) {
                $driverName = Str::before($className, '\\');
                throw new SwissechoException('Notification: '.$driverName.' driver is missing required dependencies. RUN: & '.$composerRequire .'  in CLI');
            }
        }


    }

    /**
     * @param $ch
     * @return mixed
     */
    protected function hookBeforeExecCurl($ch): mixed
    {
        if($this->isMockActive()) {
            $this->startMock();
            return null;
        }
        return $ch;
    }

    /**
     * @param $output
     * @return mixed
     */
    protected function hookAfterExecCurl($output): mixed
    {
        return $output;
    }

    /**
     * @return bool
     */
    private function isMockActive(): bool
    {
        return !$this->swissecho_config['live'];
    }

    /**
     * @return void
     */
    private function startMock() : void
    {
        $mockClass = new SwissechoMock();
        $mockClass->mockSend($this->config['class'], $this->msgBuilder);
    }

    /**
     * Run the request, or stand in for it in mock mode, and record what
     * happened as a SendResult, in formattedResponse and as an AfterSend
     * event.
     *
     * @param $ch
     * @return mixed The provider's reply on success, or an error array.
     */
    protected function execCurl($ch = null)
    {
        try {
            $ch = $this->hookBeforeExecCurl($ch);

            $outcome = $ch === null
                ? ['success' => true, 'mocked' => true, 'data' => ['message' => 'Mock mode enabled. No request was sent to gateway.']]
                : $this->performRequest($ch);
        } catch (\Exception $exception) {
            $outcome = $this->failure($exception->getMessage());
            $outcome['data'] = $exception->getMessage();
        }

        return $this->record($outcome);
    }

    /**
     * Execute the handle and let the gateway judge the reply.
     *
     * A transport error is always a failure. Past that, interpretResponse()
     * decides: by HTTP status unless the gateway knows its provider's own
     * conventions, such as an error reported inside an HTTP 200.
     *
     * @param $ch
     * @return array<string, mixed>
     */
    protected function performRequest($ch): array
    {
        $this->applyTransportDefaults($ch);

        $output = $this->responsePayload = curl_exec($ch);

        $transportError = curl_errno($ch) ? curl_error($ch) : '';
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $output = $this->hookAfterExecCurl($output);

        if($transportError !== '') {
            return $this->failure($transportError, null, ['http_code' => $httpCode]);
        }

        $verdict = $this->interpretResponse($output, $httpCode);

        if(!($verdict['success'] ?? false)) {
            return $this->failure(
                $verdict['error_message'] ?? 'Gateway rejected the message',
                $verdict['error_code'] ?? null,
                ['http_code' => $httpCode, 'response' => $output]
            );
        }

        return ['success' => true, 'data' => $output, 'message_id' => $verdict['message_id'] ?? null];
    }

    /**
     * Decide whether the provider accepted the message.
     *
     * The default trusts the HTTP status alone. Override it in a gateway
     * whose provider signals rejection another way, and return any of:
     * success (bool), message_id, error_code, error_message.
     *
     * @param mixed $body     The provider's reply, as received.
     * @param int   $httpCode
     * @return array{success: bool, message_id?: ?string, error_code?: ?string, error_message?: ?string}
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        if($this->isHttpFailure($httpCode)) {
            return [
                'success'       => false,
                'error_code'    => (string) $httpCode,
                'error_message' => 'Gateway rejected the message with HTTP '.$httpCode,
            ];
        }

        return ['success' => true];
    }

    /**
     * Decode a JSON reply for interpretResponse(), or null if it is not a
     * JSON object. Large integers stay strings: Montnets' msgid is 64-bit
     * and loses digits as a float.
     *
     * @return array<string, mixed>|null
     */
    protected function decodeJson(mixed $body): ?array
    {
        if(!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $extra Added to the legacy error array.
     * @return array<string, mixed>
     */
    private function failure(string $message, ?string $code = null, array $extra = []): array
    {
        return [
            'success'       => false,
            'error_code'    => $code,
            'error_message' => $message,
            'data'          => ['error' => true, 'message' => $message] + $extra,
        ];
    }

    /**
     * @param array<string, mixed> $outcome
     * @return mixed
     */
    private function record(array $outcome): mixed
    {
        $mocked = (bool) ($outcome['mocked'] ?? false);
        $raw = $this->responsePayload ?? null;

        $this->result = new SendResult(
            success: (bool) $outcome['success'],
            messageId: isset($outcome['message_id']) ? (string) $outcome['message_id'] : null,
            errorCode: isset($outcome['error_code']) ? (string) $outcome['error_code'] : null,
            errorMessage: $outcome['error_message'] ?? null,
            providerRes: ($mocked || $raw === false) ? null : $raw,
            to: $this->to,
            route: $this->msgBuilder->route,
            gateway: $this->msgBuilder->gateway,
            mocked: $mocked,
        );

        $this->formatResponse($this->result->success, $outcome['data']);

        AfterSend::dispatch($this->insight(), $this->getFormattedResponse(), $this->identifier, $this->result);

        return $outcome['data'];
    }

    /**
     * Enforce TLS verification and timeouts on the gateway's handle.
     *
     * Applied here, after the gateway's send() has returned, so every
     * gateway is covered and these options win over anything it set. Five
     * gateways disabled peer verification themselves, and none but the
     * Termii voice gateway set a timeout at all, so one hung provider could
     * block a PHP worker for as long as the socket stayed open.
     *
     * @param $ch
     * @return void
     */
    protected function applyTransportDefaults($ch): void
    {
        curl_setopt_array($ch, $this->transportOptions());
    }

    /**
     * The cURL options enforced on every gateway request.
     *
     * A timeout or connect_timeout of 0 is cURL's own 'wait forever', so
     * those are left off the handle rather than set to 0, which keeps any
     * value a gateway set deliberately.
     *
     * @return array
     */
    protected function transportOptions(): array
    {
        $transport = config('swissecho.transport', []);

        $verify = (bool) ($transport['verify_tls'] ?? true);

        $options = [
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];

        $timeout = (int) ($transport['timeout'] ?? 30);

        if($timeout > 0) {
            $options[CURLOPT_TIMEOUT] = $timeout;
        }

        $connectTimeout = (int) ($transport['connect_timeout'] ?? 10);

        if($connectTimeout > 0) {
            $options[CURLOPT_CONNECTTIMEOUT] = $connectTimeout;
        }

        return $options;
    }

    /**
     * A code of 0 means cURL reported no status, which happens for
     * non-HTTP handles, so only a real 4xx or 5xx counts as a failure.
     *
     * @param int $httpCode
     * @return bool
     */
    protected function isHttpFailure(int $httpCode): bool
    {
        return $httpCode >= 400;
    }


    /**
     * @return array
     */
    public function getFormattedResponse(): array
    {
        return $this->formattedResponse;
    }

    public function formatResponse( bool $status, $response): void
    {
        $this->formattedResponse = [
            'status'    =>  $status,
            'partner_response'  =>  $response,
            'from'      =>  $this->sender,
            'to'        =>  $this->to,
            'body'      =>  $this->body,
            'route'     =>  $this->msgBuilder->route,
            'gateway'   =>  $this->msgBuilder->gateway,
            'identifier'=>  $this->msgBuilder->identifier,
            'timestamp' =>  now()->toDateTimeString()
        ];

        //The same verdict in the same keys for every provider. 'status' and
        //'partner_response' above are kept as they were for existing
        //listeners; these are the ones to build on.
        if($this->result !== null) {
            $this->formattedResponse += [
                'success'       => $this->result->success,
                'message_id'    => $this->result->messageId,
                'error_code'    => $this->result->errorCode,
                'error_message' => $this->result->errorMessage,
                'provider_res'  => $this->result->providerRes,
                'mocked'        => $this->result->mocked,
            ];
        }
    }

    /**
     * Summary of insight
     * @return array
     */
    public function insight(): array
    {
        $redact = config('swissecho.redact_secrets', true);

        return [
            'request'    =>  $redact ? $this->redactSecrets($this->requestPayload) : $this->requestPayload,
            'response'   =>  $this->responsePayload
        ];
    }

    /**
     * Mask gateway credentials before the request payload leaves this class.
     *
     * insight() is dispatched as AfterSend::$insightPayload, and the README
     * tells people to log it. The payload a gateway builds is the credential
     * material: Termii returns ['api_key' => ...], while Nigerianbulksms and
     * RouteMobile return a URL carrying username= and password=.
     *
     * @param mixed $payload
     * @return mixed
     */
    protected function redactSecrets(mixed $payload): mixed
    {
        if(is_array($payload)) {
            $masked = [];
            foreach ($payload as $key => $value) {
                $masked[$key] = $this->isSecretKey((string) $key)
                    ? self::REDACTED
                    : $this->redactSecrets($value);
            }

            return $masked;
        }

        //A JSON body is the third shape a gateway builds, and the one this
        //method used to miss: Termii and Montnets return json_encode($data),
        //which contains no '=' so looksLikeQueryString() rejected it and the
        //api_key travelled in clear.
        if(is_string($payload)) {
            $decoded = json_decode($payload, true);

            if(is_array($decoded)) {
                return json_encode($this->redactSecrets($decoded));
            }
        }

        if(is_string($payload) && $this->looksLikeQueryString($payload)) {
            return preg_replace_callback(
                '/(^|[?&])([^=&]+)=([^&]*)/',
                function (array $match): string {
                    return $this->isSecretKey(urldecode($match[2]))
                        ? $match[1].$match[2].'='.self::REDACTED
                        : $match[0];
                },
                $payload
            );
        }

        return $payload;
    }

    /**
     * Only treat a string as a query string when it has no whitespace, so a
     * human-readable message body is never rewritten.
     *
     * @param string $payload
     * @return bool
     */
    protected function looksLikeQueryString(string $payload): bool
    {
        return str_contains($payload, '=') && !preg_match('/\s/', $payload);
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isSecretKey(string $key): bool
    {
        return (bool) preg_match('/^(key|auth|pass)$|api[_-]?key|password|passwd|pwd|token|secret|credential/i', $key);
    }


}
