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

    /**
     * Whether the gateway was explicitly requested by the caller
     * (as opposed to falling back to the route's default gateway).
     *
     * @var bool
     */
    protected bool $explicitGateway = false;

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
        $this->explicitGateway = $gateway !== null;
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
        return $this->gatewayConfig()['sender'] ?? $this->config['sender'] ?? null;
    }

    protected function setPlace(): void
    {
        $defaultPlace = $this->config['routes_options'][$this->route]['default_place'] ?? null;

        //Read it first and check it is usable: entering this branch on
        //method_exists alone and calling strtolower() on the result was a
        //TypeError under strict_types whenever a notifiable had no country
        //set, even with default_place configured right below.
        $notifiablePlace = ($this->notifiable && method_exists($this->notifiable, 'routeNotificationPlace'))
            ? $this->notifiable->routeNotificationPlace()
            : null;

        if ($this->msgBuilder->place) {
            $this->place = strtolower($this->msgBuilder->place);
        } else if(is_string($notifiablePlace) && $notifiablePlace !== '') {
            $this->place = strtolower($notifiablePlace);
        } else if ($defaultPlace) {
            $this->place = strtolower($defaultPlace);
        } else {
            throw SwissechoException::noPlaceConfigured(
                $this->route,
                array_keys($this->config['routes_options'][$this->route]['places'] ?? [])
            );
        }

        $this->msgBuilder->place = $this->place;
    }

    protected function setPlaceConfig(): void
    {
        $places = $this->config['routes_options'][$this->route]['places'] ?? [];

        if (!array_key_exists($this->place, $places)) {
            $availablePlaces = !empty($places) ? implode(', ', array_keys($places)) : 'none configured';

            throw new SwissechoException(
                "Swissecho: Place '{$this->place}' is not configured for the '{$this->route}' route. "
                . "Available places: {$availablePlaces}."
            );
        }

        $this->placeConfig = $places[$this->place];
    }

    /**
     * Ensure the gateway used matches the one configured for the
     * currently resolved place, unless the caller explicitly requested
     * a specific gateway.
     *
     * @return void
     */
    protected function setGatewayFromPlace(): void
    {
        if ($this->explicitGateway) {
            return;
        }

        $placeGateway = $this->placeConfig['gateway'] ?? null;

        if (!$placeGateway) {
            return;
        }

        $gatewayOptions = $this->config['routes_options'][$this->route]['gateway_options'] ?? [];

        if (!array_key_exists(strtolower($placeGateway), $gatewayOptions)) {
            throw new SwissechoException(
                "Swissecho: Gateway '{$placeGateway}' configured for place '{$this->place}' "
                . "is not configured for the '{$this->route}' route."
            );
        }

        $this->gateway($placeGateway);
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
        if ($routeBuilder->gateway !== null) {
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
        $this->setPlace();
        $this->setPlaceConfig();
        $this->setGatewayFromPlace();

        //After setGatewayFromPlace(), not before: prepareSender() falls back
        //to the gateway's configured sender, and SwissechoMessage::sender()
        //keeps the first value it is given. Resolving it first meant a send
        //routed to SMS Broadcast by place('aus') went out carrying Termii's
        //sender ID, because termii is the default gateway.
        $this->msgBuilder->sender($this->prepareSender());

        $this->setIdentifier();
        $this->msgBuilder->route($this->route);
        $this->msgBuilder->gateway($this->gateway);

        $this->pushToGateway();
    }

    protected function msgBuilderInitForDirectSend($routeBuilder): void
    {
        $this->msgBuilder = $routeBuilder;
        $this->setPlace();
        $this->setPlaceConfig();
        $this->setGatewayFromPlace();

        //See the note in msgBuilderInitForSendViaNotification().
        $this->msgBuilder->sender($this->prepareSender());

        $this->msgBuilder->route($this->route);
        $this->msgBuilder->gateway($this->gateway);
        $this->pushToGateway();
    }

    protected function pushToGateway()
    {
        if(!$this->msgBuilder->to) {
            throw new SwissechoException('Notification: Invalid phone number');
        }

        $phonecode = $this->placeConfig['phonecode'] ?? null;

        //setGatewayFromPlace() validates the place's gateway key; this one
        //was read blind. A place configured with a gateway but no phonecode
        //gave an undefined-key warning and then stripped the leading 0
        //without prepending anything, so the provider received a national
        //number and the message went nowhere.
        if(empty($phonecode)) {
            throw new SwissechoException(
                'Notification: the place "'.$this->place.'" on the '.$this->route.' route has no phonecode configured.'
            );
        }

        $this->msgBuilder->phonecode($phonecode);

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
            //A trailing comma in the recipient list, as a CSV column from the
            //database often has, left an empty entry here, and
            //addCountryCodeToPhoneNumber('', '234') returns '234' - a billed
            //message addressed to the bare country code.
            if(trim((string) $number) === '') {
                continue;
            }

            $toArr[] = addCountryCodeToPhoneNumber(trim((string) $number), $phonecode);
        }

        return $toArr;
    }

    abstract public function sendViaNotification(): static;

    abstract public function directSend($routeBuilder): static;

}
