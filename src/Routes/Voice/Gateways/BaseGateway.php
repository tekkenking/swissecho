<?php


namespace Tekkenking\Swissecho\Routes\Voice\Gateways;

use Illuminate\Support\Facades\Log;
use Tekkenking\Swissecho\SwissechoGatewayTrait;

abstract class BaseGateway
{
    use SwissechoGatewayTrait;

    /**
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);

    public function __construct($gateway_config, $payload)
    {

        $this->payload  = $payload;
        $this->config   = $gateway_config;

        //For the sms class
        $this->to = (string) $this->payload['to'];
        $this->body = (int)$this->payload['message']; //The OTP code

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
            'from'      =>  'VoiceOtp channel',
            'to'        =>  $this->to,
            'body'      =>  $this->body
        ];

        Log::info('VoiceOtp gateway class: '. get_called_class(), $this->serverResponse);
    }

}
