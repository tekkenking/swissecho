<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways;

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
    private array $setServerResponse;

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
        $this->payload          = $payload;
        $this->payload['to']    = $this->convertPhoneNumberToArray($this->payload['to']);

        //For the sms class
        $this->to = $this->payload['to'];
        $this->sender = $this->payload['sender'];
        $this->body = $this->payload['message'];
        $this->config   = $gateway_config;
    }

    /**
     * @param $phoneNm
     * @return array
     */
    protected function convertPhoneNumberToArray($phoneNm): array
    {
        return (!is_array($phoneNm))
            ? preg_split('/\s*,\s*/', trim($phoneNm))
            : $phoneNm;
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        dump($this->init());
        $ch = $this->send($this->init());
        $this->execCurl($ch);
        dd($this->getServerResponse());
    }

    /**
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);

    /**
     * @param $ch
     * @return array|bool|string|void
     */
    protected function execCurl($ch)
    {
        try {
            //get response
            $output = curl_exec($ch);

            //Print error if any
            $isError = false;
            $errorMessage = '';
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);

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
    public function setServerResponse(
        bool $status,
             $response
    ): void
    {
        $this->setServerResponse = [
            'status'    =>  $status,
            'response'  =>  $response,
            'from'      =>  $this->sender,
            'to'        =>  $this->to,
            'body'      =>  $this->body
        ];
    }

    /**
     * @return array
     */
    public function getServerResponse(): array
    {
        return $this->setServerResponse;
    }

}
