<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways;

use Illuminate\Support\Facades\Log;
//use Illuminate\Support\Str;
//use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoGatewayTrait;

abstract class BaseGateway
{
    use SwissechoGatewayTrait;

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
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);

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

}
