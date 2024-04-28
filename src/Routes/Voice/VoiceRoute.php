<?php

namespace Tekkenking\Swissecho\Routes\Voice;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class VoiceRoute extends BaseRoute
{


    public function send($notifiable, Notification $notification): static
    {
        $this->msgBuilder = $notification->toSms($notifiable);
        $this->msgBuilder->to($this->prepareTo($notifiable));
        $this->pushToGateway($notifiable);
        return $this;
    }

    public function directSend($routeBuilder): static
    {
        $this->msgBuilder = $routeBuilder;
        $this->pushToGateway($this->mockedNotifiable);
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
            if (method_exists($notifiable, 'routeNotificationSmsPhone')) {
                return $notifiable->routeNotificationSmsPhone($notifiable);
            }
        }

        return $this->msgBuilder->to;

        //throw new SwissechoException('Notification: Invalid sms phone number');
    }

    protected function getDefaultPlace()
    {
        $this->defaultPlace = array_key_first($this->config['routes_options'][$this->getRoute()]['places']);
    }

    protected function pushToGateway($notifiable = null)
    {
        if(!$this->msgBuilder->to) {
            throw new SwissechoException('Notification: Invalid sms phone number');
        }

        $this->getDefaultPlace();

        $gatewayConfig = $this->gatewayConfig();
        $place = $this->defaultPlace;

        $this->msgBuilder->gateway($this->gateway);
        $this->msgBuilder->phonecode($this->config['routes_options']['voice']['places'][$place]['phonecode']);

        if($notifiable && method_exists($notifiable, 'routeNotificationSmsCountry')) {
            $place = strtolower($notifiable->routeNotificationSmsCountry($notifiable));

            if($place) {
                if(isset($this->config['routes_options']['voice']['places'][$place])) {
                    $gatewayFromPlaceArr = $this->config['routes_options']['voice']['places'][$place];

                    //Load the gateway by place
                    $gatewayConfig = $this->config['routes_options']['voice']['gateway_options'][$gatewayFromPlaceArr['gateway']];



                    $this->msgBuilder->gateway($gatewayFromPlaceArr['gateway']);
                    $this->msgBuilder->phonecode($gatewayFromPlaceArr['phonecode']);
                }else {
                    Log::alert('SMSECHO: SMS place does not exist: '.$place, []);
                }

            }

        }

        $this->msgBuilder->place($place);
        $this->msgBuilder->to($this->prepTo($this->msgBuilder->to, $this->msgBuilder->phonecode));
        $this->config['route'] = 'voice';

        if($this->config['live'] == false) {
            $this->mockSend($gatewayConfig, $this->msgBuilder);
        } else {
            $gatewayClass = $gatewayConfig['class'];
            (new $gatewayClass($gatewayConfig, $this->msgBuilder->get()))->boot();
        }

    }

    private function prepTo(string $to, $phonecode): array
    {
        return [add_country_code($to, $phonecode)];
    }

}
