<?php

namespace Tekkenking\Swissecho\Routes\Sms;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class SmsRoute extends BaseRoute
{

    protected $defaultPlace;

    /**
     * @param $notifiable
     * @param Notification $notification
     * @return SmsRoute
     * @throws SwissechoException
     */
    public function send($notifiable, Notification $notification)
    {
        $this->msgBuilder = $notification->toSms($notifiable);
        $this->msgBuilder->to($this->prepareTo($notifiable));
        $this->msgBuilder->sender($this->prepareSender($notifiable));
        $this->setIdentifier($notifiable);
        $this->pushToGateway($notifiable);
        return $this;
    }

    public function setIdentifier($notifiable)
    {
        if(!isset($this->msgBuilder->identifier)) {
            $this->msgBuilder->identifier($notifiable);
        }
    }

    /**
     * @param $routeBuilder
     * @throws SwissechoException
     */
    public function directSend($routeBuilder)
    {
        $this->msgBuilder = $routeBuilder;
        $this->msgBuilder->sender($this->prepareSender());
        return $this->pushToGateway($this->mockedNotifiable);
    }

    protected function getDefaultPlace()
    {
        $this->defaultPlace = array_key_first($this->config['routes_options'][$this->getRoute()]['places']);
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

    /**
     * Get the alphanumeric sender.
     *
     * @param $notifiable
     * @return mixed
     */
    protected function prepareSender($notifiable = null): mixed
    {
        //dd($this->gatewayConfig());
        if(!$this->msgBuilder->sender ) {

            if ($notifiable
                && method_exists($notifiable, 'routeNotificationSmsSender')) {
                return $notifiable->routeNotificationSmsSender($notifiable);
            }

            $gatewaySender = $this->gatewaySender();
            if($gatewaySender) {
                return $gatewaySender;
            }
        }

        return $this->msgBuilder->sender;
    }

    protected function pushToGateway($notifiable = null)
    {
        if(!$this->msgBuilder->to) {
            throw new SwissechoException('Notification: Invalid sms phone number');
        }

        if(!$place = $this->msgBuilder->place) {
            $this->getDefaultPlace();
            $place = $this->defaultPlace;
        }


        $gatewayConfig = $this->gatewayConfig();


        $this->msgBuilder->gateway($this->gateway);
        $this->msgBuilder->phonecode($this->config['routes_options']['sms']['places'][$place]['phonecode']);

        if($notifiable && method_exists($notifiable, 'routeNotificationSmsCountry')) {
            $place = strtolower($notifiable->routeNotificationSmsCountry($notifiable));

            if($place) {
                if(isset($this->config['routes_options']['sms']['places'][$place])) {
                    $gatewayFromPlaceArr = $this->config['routes_options']['sms']['places'][$place];

                    //Load the gateway by place
                    //dd($gatewayFromPlaceArr);
                    $gatewayConfig = $this->config['routes_options']['sms']['gateway_options'][$gatewayFromPlaceArr['gateway']];



                    $this->msgBuilder->gateway($gatewayFromPlaceArr['gateway']);
                    $this->msgBuilder->phonecode($gatewayFromPlaceArr['phonecode']);
                }else {
                    Log::alert('SMSECHO: SMS place does not exist: '.$place, []);
                }

            }
        }

        $this->msgBuilder->place($place);
        $this->msgBuilder->to($this->prepTo($this->msgBuilder->to, $this->msgBuilder->phonecode));
        $this->config['route'] = 'sms';

        $gatewayClass = $gatewayConfig['class'];

        if($this->config['live'] == false) {
            $mockResponseArr = $this->mockSend($gatewayConfig, $this->msgBuilder);
            $coldBoot = (new $gatewayClass($gatewayConfig, $this->msgBuilder->get()))->coldBoot();
            $coldBoot->setServerResponse(true, $mockResponseArr['response']);
            return $coldBoot;

        } else {
            return (new $gatewayClass($gatewayConfig, $this->msgBuilder->get()))->boot();
        }

    }

    private function prepTo($to, $phonecode): array
    {
        if(!is_array($to)) {
            $to = explode(',', $to);
        }

        $toArr = [];
        foreach ($to ?? [] as $number) {
            $toArr[] = add_country_code($number, $phonecode);
        }

        return $toArr;
    }

}
