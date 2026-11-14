<?php

namespace Tekkenking\Swissecho;

/**
 * For gateways whose provider accepts one recipient per request.
 *
 * Both WhatsApp APIs and both voice providers take a single recipient, so a
 * multi-recipient send needs one request each. Without this, a gateway reads
 * only the first number and the rest are dropped with no error, while
 * formatResponse() still reports the whole list as delivered.
 */
trait SendsPerRecipient
{
    /**
     * The recipient the current request is for.
     *
     * @var string|null
     */
    protected ?string $currentRecipient = null;

    /**
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
            throw new SwissechoException('Notification: no recipient to send to.');
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
