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

        $this->route = $route ?? $this->getDefaultRoute();
        return $this;
    }


    protected function getRoute(): string
    {
        return strtolower($this->route);
    }

    protected function getDefaultGateway()
    {
        return $this->config['routes_options'][$this->getRoute()]['gateway'];
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
        $this->gatewayConfig = $this->config['routes_options'][$this->getRoute()]['gateway_options'][$this->getGateway()];
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
        return $this->gatewayConfig()['sender'];
    }

    /**
     * @param $notifiable
     * @param Notification $notification
     * @return void
     */
    public function bootByNotification($notifiable, Notification $notification): void
    {

        $this->send($notifiable, $notification);
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


    abstract public function send($notifiable, Notification $notification): static;

    abstract public function directSend($routeBuilder): static;

}
