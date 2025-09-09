<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes;

use Illuminate\Notifications\Notification;

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
    protected mixed $msgBuilder;

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
        $this->send();
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

    protected function prepTo($to, $phonecode): array
    {
        $to = convertPhoneNumberToArray($to);

        $toArr = [];
        foreach ($to ?? [] as $number) {
            $toArr[] = addCountryCodeToPhoneNumber($number, $phonecode);
        }

        return $toArr;
    }


    abstract public function send(): static;

    abstract public function directSend($routeBuilder): static;

}
