<?php


namespace Tekkenking\Swissecho\Routes\Voice\Gateways;

use Tekkenking\Swissecho\SendsPerRecipient;
use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoMessage;

abstract class BaseGateway
{
    //SendsPerRecipient::boot() replaces the single-request boot() in
    //SwissechoGatewayTrait, because this provider takes one recipient per
    //request.
    use SwissechoGatewayTrait, SendsPerRecipient {
        SendsPerRecipient::boot insteadof SwissechoGatewayTrait;
    }

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

        //The whole list. SendsPerRecipient::boot() narrows $this->to to one
        //number per request; taking reset() here meant every recipient after
        //the first was dropped silently while the event reported them all.
        $this->to = $this->payload['to'] ?? null;

        $this->sender = (string) ($this->payload['sender'] ?? '');

        //The OTP code. Cast to string, not int: (int) '012345' is 12345,
        //which silently breaks any zero-padded or alphanumeric code.
        $this->body = (string) $this->payload['message'];
    }

}
