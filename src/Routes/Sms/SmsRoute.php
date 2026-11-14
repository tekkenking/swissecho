<?php

namespace Tekkenking\Swissecho\Routes\Sms;

use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class SmsRoute extends BaseRoute
{

    /**
     * @return static
     * @throws SwissechoException
     */
    public function sendViaNotification(): static
    {
        $this->prepareAndSend($this->notification->toSms($this->notifiable));
        return $this;
    }

    /**
     * @param $routeBuilder
     * @return $this
     * @throws SwissechoException
     */
    public function directSend($routeBuilder): static
    {
        $this->prepareAndSend($routeBuilder);
        return $this;
    }

    /**
     * Get the alphanumeric sender.
     * @return mixed
     */
    protected function prepareSender(): mixed
    {
        if($this->msgBuilder->sender) {
            return $this->msgBuilder->sender;
        }

        return $this->senderFromNotifiable(['routeNotificationSmsSender']) ?: $this->gatewaySender();
    }
}
