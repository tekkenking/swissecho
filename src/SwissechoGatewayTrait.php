<?php

namespace Tekkenking\Swissecho;

use Illuminate\Support\Str;
use Tekkenking\Swissecho\Events\AfterSend;

trait SwissechoGatewayTrait
{

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
        return [
            'request'    =>  $this->requestPayload,
            'response'   =>  $this->responsePayload
        ];
    }


}
