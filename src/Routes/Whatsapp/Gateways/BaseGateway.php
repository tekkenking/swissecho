<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp\Gateways;

use Tekkenking\Swissecho\SendsPerRecipient;
use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoMessage;

abstract class BaseGateway
{
    //SendsPerRecipient::boot() replaces the single-request boot() in
    //SwissechoGatewayTrait, because every WhatsApp API here takes one
    //recipient per request.
    use SwissechoGatewayTrait, SendsPerRecipient {
        SendsPerRecipient::boot insteadof SwissechoGatewayTrait;
    }

    /**
     * @param $gateway_config
     * @param SwissechoMessage $msgBuilder
     */
    public function __construct($gateway_config, SwissechoMessage $msgBuilder)
    {
        $this->msgBuilder = $msgBuilder;

        $this->payload = $this->msgBuilder->get();
        $this->to = $this->payload['to'];
        $this->sender = (string) ($this->payload['sender'] ?? '');
        $this->body = $this->payload['message'];
        $this->config   = $gateway_config;
    }

    /**
     * @return mixed
     */
    abstract public function init(): mixed;

    abstract public function send($data);
}
