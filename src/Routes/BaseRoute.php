<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes;

use Illuminate\Notifications\Notification;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;

abstract class BaseRoute
{
    /**
     * @var  array
     */
    protected array $config;

    /**
     * @var string
     */
    protected string $gateway;

    /**
     * @var string
     */
    protected string $route;

    /**
     * @var mixed
     */
    protected SwissechoMessage $msgBuilder;

    /**
     * @var array
     */
    private array $gatewayConfig;

    /**
     * @var mixed
     */
    protected string $defaultPlace;

    protected string $place;

    protected array $placeConfig;

    protected $notifiable;

    protected Notification $notification;

    /**
     * @return void
     */
    private function loadConfig(): void
    {
        $this->config = config('swissecho');
    }

    private function getDefaultRoute()
    {
        return $this->config['route'];
    }

    /**
     * @param string $route
     * @return $this
     */
    public function route(string $route): static
    {
        $this->loadConfig();
        $this->route = strtolower($route ?? $this->getDefaultRoute());
        return $this;
    }

    protected function getDefaultGateway()
    {
        return array_key_first($this->config['routes_options'][$this->route]['gateway_options']);
    }

    protected function getGateway(): string
    {
        return strtolower($this->gateway);
    }

    /**
     * @return void
     */
    private function loadGatewayConfig(): void
    {
        $this->gatewayConfig = $this->config['routes_options'][$this->route]['gateway_options'][$this->getGateway()];
    }

    /**
     * @param string|null $gateway
     * @return $this
     */
    public function gateway(string | null $gateway = null): static
    {
        $this->gateway = $gateway ?? $this->getDefaultGateway();

        $this->loadGatewayConfig();
        return $this;
    }

    /**
     * @return array
     */
    protected function gatewayConfig(): array
    {
        return $this->gatewayConfig;
    }

    protected function gatewaySender()
    {
        return $this->gatewayConfig()['sender'] ?? 'null';
    }

    protected function setPlace(): void
    {
        if ($this->msgBuilder->place) {
            $this->place = $this->msgBuilder->place;
        } else if($this->notifiable && method_exists($this->notifiable, 'routeNotificationPlace')) {
            $this->place = strtolower($this->notifiable->routeNotificationPlace());
        } else {
            $this->place = array_key_first($this->config['routes_options'][$this->route]['places']);
        }

        $this->msgBuilder->place = $this->place;
    }

    protected function setPlaceConfifg(): void
    {
        $this->placeConfig = $this->config['routes_options'][$this->route]['places'][$this->place];
    }

    /**
     * @param $notifiable
     * @param Notification $notification
     * @return void
     */
    public function bootByNotification($notifiable, Notification $notification): void
    {
        $this->notifiable = $notifiable;
        $this->notification = $notification;
        $this->sendViaNotification();
    }

    /**
     * @param $routeBuilder
     * @return void
     */
    public function bootByDirect($routeBuilder): void
    {
        $this->loadConfig();
        if($routeBuilder->gateway) {
            $this->gateway($routeBuilder->gateway);
        }
        $this->directSend($routeBuilder);
    }

    public function setIdentifier(): void
    {
        if(isset($this->msgBuilder->identifier)) {
            return;
        }

        if(isset($this->notifiable)) {
            $this->msgBuilder->identifier($this->notifiable);
        }
    }

    protected function msgBuilderInitForSendViaNotification($viaMethod): void
    {
        $this->msgBuilder = $viaMethod;
        $this->msgBuilder->to($this->prepareTo());
        $this->msgBuilder->sender($this->prepareSender());
        $this->setPlace();
        $this->setPlaceConfifg();
        $this->setIdentifier();
        $this->msgBuilder->route($this->route);
        $this->msgBuilder->gateway($this->gateway);

        $this->pushToGateway();
    }

    protected function msgBuilderInitForDirectSend($routeBuilder): void
    {
        $this->msgBuilder = $routeBuilder;
        $this->msgBuilder->sender($this->prepareSender());
        $this->setPlace();
        $this->setPlaceConfifg();
        $this->msgBuilder->route($this->route);
        $this->msgBuilder->gateway($this->gateway);
        $this->pushToGateway();
    }

    protected function pushToGateway()
    {
        if(!$this->msgBuilder->to) {
            throw new SwissechoException('Notification: Invalid phone number');
        }

        $this->msgBuilder->phonecode($this->placeConfig['phonecode']);

        $this->msgBuilder->to($this->addPhoneCodeToPhoneNumberArr($this->convertPhoneNumberToArray($this->msgBuilder->to), $this->msgBuilder->phonecode));
        $gatewayConfig = $this->gatewayConfig();
        $gatewayClass = $gatewayConfig['class'];

        (new $gatewayClass($gatewayConfig, $this->msgBuilder))->boot();
    }

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

    protected function convertPhoneNumberToArray($to): array
    {
        return convertPhoneNumberToArray($to);
    }

    protected function addPhoneCodeToPhoneNumberArr(array $tos, $phonecode): array
    {
        $toArr = [];
        foreach ($tos ?? [] as $number) {
            $toArr[] = addCountryCodeToPhoneNumber($number, $phonecode);
        }

        return $toArr;
    }

    abstract public function sendViaNotification(): static;

    abstract public function directSend($routeBuilder): static;

}
