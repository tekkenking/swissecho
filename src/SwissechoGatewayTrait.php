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
    public string $sender;

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

    public function coldBoot(): self
    {
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
     * @param $ch
     * @return array|bool|string|void
     */
    protected function execCurl($ch = null)
    {

       /* if(!$ch) {

        }*/

        try {
            //get response
            $ch = $this->hookBeforeExecCurl($ch);

            if($ch === null) {
                //Meaning we are running mock mode
                $status = true;
                $data = ['message' => 'Mock mode enabled. No request was sent to gateway.'];
            } else {
                //dd('should not be here');
                $output = $this->responsePayload = curl_exec($ch);

                //Print error if any
                $isError = false;
                $errorMessage = '';
                if (curl_errno($ch)) {
                    $isError = true;
                    $errorMessage = curl_error($ch);
                }
                curl_close($ch);

                $output = $this->hookAfterExecCurl($output);

                if($isError){
                    $data = ['error' => true , 'message' => $errorMessage];
                }else{
                    $data = $output;
                }

                $status = !$isError;
            }

            $this->formatResponse($status, $data);

            AfterSend::dispatch($this->insight(), $this->getFormattedResponse(), $this->identifier);

            return $data;

        } catch (\Exception $exception) {
            $this->formatResponse(false, $exception->getMessage());
            AfterSend::dispatch($this->insight(), $this->getFormattedResponse(), $this->identifier);
        }
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
