<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class WhatsappRoute extends BaseRoute
{


    /**
     * @return static
     * @throws SwissechoException
     */
    public function sendViaNotification(): static
    {
        $this->prepareAndSend($this->notification->toWhatsapp($this->notifiable));
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
     * @return mixed
     * @throws SwissechoException
     */

    /**
     * Get the alphanumeric sender.
     *
     * @return mixed
     */
    protected function prepareSender(): mixed
    {
        if($this->msgBuilder->sender) {
            return $this->msgBuilder->sender;
        }

        return $this->senderFromNotifiable(['routeNotificationWhatsappSender', 'routeNotificationSmsSender'])
            ?: $this->gatewaySender();
    }

}
