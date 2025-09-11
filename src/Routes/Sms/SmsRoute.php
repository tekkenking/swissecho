<?php

namespace Tekkenking\Swissecho\Routes\Sms;

use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class SmsRoute extends BaseRoute
{

    /**
     * @return SmsRoute
     * @throws SwissechoException
     */
    public function sendViaNotification(): static
    {
        $this->msgBuilderInitForSendViaNotification($this->notification->toSms($this->notifiable));
        return $this;
    }

    /**
     * @param $routeBuilder
     * @return $this
     * @throws SwissechoException
     */
    public function directSend($routeBuilder): static
    {
        $this->msgBuilderInitForDirectSend($routeBuilder);
        return $this;
    }

    /**
     * Get the alphanumeric sender.
     * @return mixed
     */
    protected function prepareSender(): mixed
    {
        if(!$this->msgBuilder->sender ) {

            if ($this->notifiable
                && method_exists($this->notifiable, 'routeNotificationSmsSender')) {
                return $this->notifiable->routeNotificationSmsSender();
            }

            return $this->gatewaySender();
        }

        return $this->msgBuilder->sender;
    }
}
