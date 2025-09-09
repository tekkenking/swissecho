<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp\Gateways;

use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoMessage;

abstract class BaseGateway
{
    use SwissechoGatewayTrait;

    /**
     * @param $gateway_config
     * @param SwissechoMessage $msgBuilder
     */
    public function __construct($gateway_config, SwissechoMessage $msgBuilder)
    {
        $this->msgBuilder = $msgBuilder;

        $this->payload = $this->msgBuilder->get();
        //For the sms class
        $this->to = $this->payload['to'];
        $this->sender = $this->payload['sender'];
        $this->body = $this->payload['message'];
        $this->config   = $gateway_config;
    }

    /**
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);

}
