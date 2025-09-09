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
    public function send(): static
    {
        $this->msgBuilder = $this->notification->viaWhatsapp($this->notifiable);
        $this->msgBuilder->to($this->prepareTo());
        $this->msgBuilder->sender($this->prepareSender());
        $this->setPlace();
        $this->setPlaceConfifg();
        $this->pushToGateway();
        return $this;
    }

    /**
     * @param $routeBuilder
     * @return $this
     * @throws SwissechoException
     */
    public function directSend($routeBuilder): static
    {
        $this->msgBuilder = $routeBuilder;
        $this->msgBuilder->sender($this->prepareSender());
        $this->setPlace();
        $this->setPlaceConfifg();
        $this->pushToGateway();
        return $this;
    }

    /**
     * @return mixed
     * @throws SwissechoException
     */
    protected function prepareTo(): mixed
    {
        if(!$this->msgBuilder->to) {

            // THIS IS FROM DB TABLE
            if (isset($this->notifiable->phone)) {
                return $this->notifiable->phone;
            }

            // THIS IS FROM THE CURRENT MODEL
            if (method_exists($this->notifiable, 'routeNotificationPhone')) {
                return $this->notifiable->routeNotificationPhone();
            }
        }

        return $this->msgBuilder->to;
    }

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

    protected function pushToGateway()
    {
        if(!$this->msgBuilder->to) {
            throw new SwissechoException('Notification: Invalid whatsapp phone number');
        }

        $this->msgBuilder->to($this->prepTo($this->msgBuilder->to, $this->placeConfig['phonecode']));

        $gatewayConfig = $this->gatewayConfig();
        $gatewayClass = $gatewayConfig['class'];
        (new $gatewayClass($gatewayConfig, $this->msgBuilder->get()))->boot();

    }


}
