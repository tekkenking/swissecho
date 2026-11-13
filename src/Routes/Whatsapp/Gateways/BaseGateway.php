<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp\Gateways;

use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoGatewayTrait;
use Tekkenking\Swissecho\SwissechoMessage;

abstract class BaseGateway
{
    use SwissechoGatewayTrait;

    /**
     * The recipient the current request is for.
     *
     * @var string|null
     */
    protected ?string $currentRecipient = null;

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

    /**
     * Send one request per recipient.
     *
     * Every WhatsApp API here takes a single recipient per request, so a
     * multi-recipient send needs one request each. Both gateways used to
     * read only the first number and drop the rest silently.
     *
     * @return self
     * @throws SwissechoException
     */
    public function boot(): self
    {
        $this->swissecho_config = config('swissecho');
        $this->processDependencies();

        //Captured before the loop, because the loop replaces $this->to with
        //the recipient of each individual request.
        $recipients = $this->recipients();

        if($recipients === []) {
            throw new SwissechoException('Whatsapp: no recipient to send to.');
        }

        foreach ($recipients as $recipient) {
            $this->currentRecipient = $recipient;

            //So the AfterSend event for each request names the number that
            //request actually went to, rather than the whole list.
            $this->to = $recipient;

            $this->requestPayload = $this->init();
            $ch = $this->send($this->requestPayload);
            $this->execCurl($ch ?? null);
        }

        return $this;
    }

    /**
     * Every recipient, trimmed, with blanks dropped.
     *
     * @return array
     */
    protected function recipients(): array
    {
        $list = is_array($this->to) ? $this->to : [$this->to];

        $clean = [];

        foreach ($list as $entry) {
            //A comma-separated string is a documented input to to(), and
            //BaseRoute::pushToGateway() normally splits it before we get
            //here. Split again so a gateway constructed directly is also
            //correct.
            foreach (explode(',', (string) $entry) as $number) {
                $number = trim($number);

                if($number !== '') {
                    $clean[] = $number;
                }
            }
        }

        return $clean;
    }
}
