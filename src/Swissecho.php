<?php

namespace Tekkenking\Swissecho;

use Illuminate\Notifications\Notification;

/**
 *  $sw->route('sms')
 */

class Swissecho
{

    /**
     * @var
     */
    private $notifiable;

    /**
     * @var Notification
     */
    private Notification $notification;

    /**
     * @var array|string[]
     */
    private array $vias = ['sms', 'slack', 'whatsapp'];

    private $initRoute;

    private $isCallBack = false;

    private $echoBuilderMessage;

    private $gateway;

    private $route;


    public $message;

    public $to;

    public $sender;

    public $directNotifiable;


    /**
     * @param $notifiable
     * @param Notification $notification
     * @return void
     * @throws SwissechoException
     */
    public function send($notifiable, Notification $notification): void
    {
        $this->notifiable = $notifiable;
        $this->notification = $notification;

        if (method_exists($notification, 'swissechoRoutes')) {
            $this->vias = $notification->swissechoRoutes($notifiable);
        }
        
        $this->makeRoutes();
    }

    /**
     * @return void
     * @throws SwissechoException
     */
    protected function makeRoutes(): void
    {
        foreach ($this->vias as $method) {

            //Uppercase first letter
            $route= ucfirst($method);

            //Prefix with "via"
            $viaMethod = 'to'.$route;

            //Check if viaMethod exists in the notification class
            if(method_exists($this->notification, $viaMethod)) {
                $this->route($route)->go();
            }
        }
    }

    /**
     * @return SwissechoMessage
     */
    private function echoBuilder(): SwissechoMessage
    {
        return new SwissechoMessage();
    }

    /**
     * @param $name
     * @return string
     */
    private function namespace($name): string
    {
        return "Tekkenking\\Swissecho\\Routes\\".$name;
    }

    /**
     * @return false|string
     */
    protected function isRouteAvailable(): bool|string
    {
        $name = $this->route;
        //Get namespace
        $namespace = $this->namespace($name);

        //Put class together
        $class = $namespace."\\".$name."Route";

        //dd($class);

        //Check if class exist
        if(class_exists($class)) {
            return $class;
        }

        return false;
    }


    /**
     * @param string|null $route
     * @param string|callable|null $callBack
     * @return ?Swissecho
     * @throws SwissechoException
     */
    public function route(string $route = null, string | callable $callBack = null): ?Swissecho
    {
        $this->route = ucfirst($route ??  config('swissecho.route'));
        $routeClass = $this->isRouteAvailable();
        if($routeClass) {
            $this->initRoute = new $routeClass;

            if($callBack) {

                if(is_string($callBack)) {
                    $this->message($callBack);
                    $callBack = $this->message;
                }

                $this->isCallBack = true;
                $this->echoBuilderMessage = $callBack($this->echoBuilder());
            }
            return $this;

        } else {
            //Through exception for unknown via method with swissecho
            throw new SwissechoException('Route not found');
        }
    }

    /**
     * @param string $gateway
     * @return Swissecho
     */
    public function gateway(string $gateway): Swissecho
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @return mixed
     * @throws SwissechoException
     */
    public function go(): mixed
    {

        //checking if initRoute is already initiated
        if(!$this->route) {
            $this->route(null, $this->message);
        }

        //this is important for direct message sending
        if($this->to || $this->message || $this->sender) {
            $this->route($this->route, $this->message);
        }

        $prepped = $this->initRoute
            ->route($this->route)
            ->gateway($this->gateway);

        if($this->isCallBack) {
            //check if "to" was not set at echoBuilderMessage and $this->to property was set
            if(!$this->echoBuilderMessage->to && $this->to) {
                //If its was not set, then we set the $this->to
                $this->echoBuilderMessage->to($this->to);
            }

            //check if "to" was not set at echoBuilderMessage and $this->to property was set
            if(!$this->echoBuilderMessage->sender && $this->sender) {
                //If its was not set, then we set the $this->to
                $this->echoBuilderMessage->sender($this->sender);
            }

            return $prepped->bootByDirect($this->echoBuilderMessage);
        } else {
            $this->notifiable = $this->directNotifiable ?? $this->notifiable;
            return $prepped->bootByNotification($this->notifiable, $this->notification);
        }

    }

    /**
     * @throws SwissechoException
     */
    public function quick($phoneNumber, $message): void
    {
        //get default route
        $this->route(null, function(SwissechoMessage $ms) use ($phoneNumber, $message) {
           return $ms->line($message)
               ->to($phoneNumber);
        })->go();
    }

    /**
     * @throws SwissechoException
     */
    public function message(string | callable $message): Swissecho
    {
        $this->message = $message;

        if(is_string($this->message)) {
            $this->message = function(SwissechoMessage $ms) use ($message) {
                return $ms->line($message);
            };
        } else if(!is_callable($this->message)) {
            throw new SwissechoException('Invalid message passed in');
        }

        return $this;
    }

    public function to($phoneNumber)
    {
        $this->to = $phoneNumber;
        return $this;
    }

    public function sender($sender)
    {
        $this->sender = $sender;
        return $this;
    }

    public function makeNotifiable($notifiable)
    {
        $this->directNotifiable = $notifiable;
        return $this;
    }


}
