<?php

namespace Tekkenking\Swissecho\Routes\Sms;

use Illuminate\Notifications\Notification;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class SmsRoute extends BaseRoute
{


    /**
     * @param $notifiable
     * @param Notification $notification
     * @return SmsRoute
     * @throws SwissechoException
     */
    public function send($notifiable, Notification $notification): static
    {
        $this->msgBuilder = $notification->viaSms($notifiable);
        $this->msgBuilder->to($this->prepareTo($notifiable));
        $this->msgBuilder->sender($this->prepareSender($notifiable));
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
        $this->pushToGateway();
        return $this;
    }

    /**
     * @param $notifiable
     * @return mixed
     * @throws SwissechoException
     */
    protected function prepareTo($notifiable): mixed
    {
        if(!$this->msgBuilder->to) {

            // THIS IS FROM TEMPUSERS TABLE
            if (isset($notifiable->phone)) {
                return $notifiable->phone;
            }

            // THIS IS FROM THE CURRENT MODEL
            if (method_exists($notifiable, 'routeNotificationPhone')) {
                return $notifiable->routeNotificationPhone($notifiable);
            }
        }

        return $this->msgBuilder->to;

        //throw new SwissechoException('Notification: Invalid sms phone number');
    }

    /**
     * Get the alphanumeric sender.
     *
     * @param $notifiable
     * @return mixed
     */
    protected function prepareSender($notifiable = null): mixed
    {
        if(!$this->msgBuilder->sender ) {

            if ($notifiable
                && method_exists($notifiable, 'routeNotificationSmsSender')) {
                return $notifiable->routeNotificationSmsSender($notifiable);
            }

            return $this->gatewaySender();
        }

        return $this->msgBuilder->sender;
    }

    protected function pushToGateway()
    {
        if(!$this->msgBuilder->to) {
            throw new SwissechoException('Notification: Invalid sms phone number');
        }

        $gatewayConfig = $this->gatewayConfig();
        $gatewayClass = $gatewayConfig['class'];
        (new $gatewayClass($gatewayConfig, $this->msgBuilder->get()))->boot();

    }


}
