<?php


namespace Tekkenking\Swissecho\Routes\Voice\Gateways;

use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoMessage;

abstract class BaseGateway
{
    use SwissechoGatewayTrait;

    /**
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);

    /**
     * @param $gateway_config
     * @param SwissechoMessage $msgBuilder
     */
    public function __construct($gateway_config, SwissechoMessage $msgBuilder)
    {
        $this->msgBuilder = $msgBuilder;

        $this->payload  = $this->msgBuilder->get();
        $this->config   = $gateway_config;

        //For the voice class
        $to = $this->payload['to'] ?? null;
        $this->to = (string) (is_array($to) ? reset($to) : $to);

        $this->sender = (string) ($this->payload['sender'] ?? '');

        $this->body = (int) $this->payload['message']; //The OTP code
    }

}
