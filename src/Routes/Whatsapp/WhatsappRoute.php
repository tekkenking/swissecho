<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class WhatsappRoute extends BaseRoute
{


    /**
     * @return WhatsappRoute
     * @throws SwissechoException
     */
    public function sendViaNotification(): static
    {
        $this->msgBuilderInitForSendViaNotification($this->notification->toWhatsapp($this->notifiable));
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
        if(!$this->msgBuilder->sender ) {

            if ($this->notifiable
                && method_exists($this->notifiable, 'routeNotificationWhatsappSender')) {
                return $this->notifiable->routeNotificationWhatsappSender();
            } elseif ($this->notifiable
                && method_exists($this->notifiable, 'routeNotificationSmsSender')) {
                return $this->notifiable->routeNotificationSmsSender();
            }

            return $this->gatewaySender();
        }

        return $this->msgBuilder->sender;
    }

}
