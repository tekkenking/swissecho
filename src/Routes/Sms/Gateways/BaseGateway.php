<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways;

use App\Channels\Sms\Exceptions\BcSmsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tekkenking\Swissecho\SwissechoException;

abstract class BaseGateway
{
    /**
     * @var array
     */
    public array $to;

    /**
     * @var string|mixed
     */
    public string $sender;

    /**
     * @var string
     */
    public string $body;

    /**
     * @var array
     */
    private array $serverResponse;

    /**
     * @var array
     */
    private array $payload;

    /**
     * @var array
     */
    public array $config;

    /**
     * @param $gateway_config
     * @param $payload
     */
    public function __construct($gateway_config, $payload)
    {

        $this->payload  = $payload;
        $this->config   = $gateway_config;

        //$this->payload['to']    = $this->convertPhoneNumberToArray($this->payload['to']);

        //For the sms class
        $this->to = $this->payload['to'];
        $this->sender = $this->payload['sender'];
        $this->body = $this->payload['message'];

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
    private function processDependencies(): void
    {

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
     * @param $phoneNm
     * @return array
     */
//    protected function convertPhoneNumberToArray($phoneNm): array
//    {
//        return (!is_array($phoneNm))
//            ? preg_split('/\s*,\s*/', trim($phoneNm))
//            : $phoneNm;
//    }

    /**
     * @return void
     */
    public function boot(): void
    {
        //dump($this->init());
        $this->processDependencies();
        $ch = $this->send($this->init());
        $this->execCurl($ch ?? null);
        //dd($this->getServerResponse());
    }

    /**
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);

    /**
     * @param $ch
     * @return mixed
     */
    protected function hookBeforeExecCurl($ch)
    {
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
     * @param $ch
     * @return array|bool|string|void
     */
    protected function execCurl($ch = null)
    {

        if(!$ch) {

        }

        try {
            //get response
            $ch = $this->hookBeforeExecCurl($ch);

            $output = curl_exec($ch);

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
            $this->setServerResponse($status, $data);

            return $data;

        } catch (\Exception $exception) {
            $this->setServerResponse(false, $exception->getMessage());
        }
    }

    /**
     * @param bool $status
     * @param $response
     * @return void
     */
    public function setServerResponse(bool $status, $response): void
    {
        $this->serverResponse = [
            'status'    =>  $status,
            'response'  =>  $response,
            'from'      =>  $this->sender,
            'to'        =>  $this->to,
            'body'      =>  $this->body
        ];

        Log::info('SMS gateway class: '. get_called_class(), $this->serverResponse);
    }

    /**
     * @return array
     */
    public function getServerResponse(): array
    {
        return $this->serverResponse;
    }

}
