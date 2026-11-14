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
    private ?Notification $notification = null;

    /**
     * @var array|string[]
     */
    private const DEFAULT_VIAS = ['sms', 'slack', 'whatsapp', 'telegram', 'voice'];

    private array $vias = self::DEFAULT_VIAS;

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
     * Send a notification on every route it supports.
     *
     * @param $notifiable
     * @param Notification $notification
     * @return array<string, SendResult|null> One result per route, keyed by route.
     *                                        Laravel passes this on as the
     *                                        NotificationSent event's response.
     * @throws SwissechoException
     */
    public function send($notifiable, Notification $notification): array
    {
        $this->notifiable = $notifiable;
        $this->notification = $notification;

        //Always reassign: this object is a singleton and is reused for every
        //notification, so a previous notification's swissechoRoutes() would
        //otherwise still be in effect here.
        $this->vias = method_exists($notification, 'swissechoRoutes')
            ? $notification->swissechoRoutes($notifiable)
            : self::DEFAULT_VIAS;
        
        try {
            return $this->makeRoutes();
        } finally {
            //Cleared here rather than in go(): makeRoutes() reads
            //$this->notification once per via, so go() must not take it away
            //mid-loop. Leaving it set let a later bare go() re-send the whole
            //notification, a second billed message with no new instruction.
            $this->notifiable = null;
            $this->notification = null;
        }
    }

    /**
     * @return array<string, SendResult|null> One result per route sent on, keyed by route.
     * @throws SwissechoException
     */
    protected function makeRoutes(): array
    {
        $results = [];

        foreach ($this->vias as $method) {

            //Uppercase first letter
            $route= ucfirst($method);

            //Prefix with "via"
            $viaMethod = 'to'.$route;

            //Check if viaMethod exists in the notification class
            if(method_exists($this->notification, $viaMethod)) {
                $results[strtolower($method)] = $this->route($route)->go();
            }
        }

        return $results;
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
    public function route(?string $route = null, string|callable|null $callBack = null): ?Swissecho
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
     * Send, and say what happened.
     *
     * @return SendResult|null The provider's verdict in one shape for every
     *                         gateway. Null only for a custom gateway that
     *                         does not report one.
     * @throws SwissechoException
     */
    public function go(): mixed
    {
        try {
            return $this->dispatch();
        } finally {
            //This object is a container singleton, so anything left set here
            //would leak into the next send.
            $this->resetSendState();
        }
    }

    /**
     * Clear the per-send state, so one send cannot affect the next.
     *
     * @return void
     */
    private function resetSendState(): void
    {
        $this->route = null;
        $this->gateway = null;
        $this->to = null;
        $this->sender = null;
        $this->message = null;
        $this->isCallBack = false;
        $this->echoBuilderMessage = null;

        //Without this, makeNotifiable() survived the send that used it, and
        //go() prefers $directNotifiable over the notifiable passed to a later
        //send() - so the next person's message went to the previous person.
        $this->directNotifiable = null;

        //$initRoute is deliberately left in place: it is the artifact of the
        //send just made, which tests and debugging read, and resetting $route
        //above guarantees go() rebuilds it before the next send uses it.
    }

    /**
     * @return mixed
     * @throws SwissechoException
     */
    private function dispatch(): mixed
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
            return $prepped->bootByDirect(
                $this->builderForDirectSend(),
                $this->directNotifiable ?? $this->notifiable
            );
        }

        if(!isset($this->notification)) {
            throw new SwissechoException('Nothing to send: give route() a message or a callback, or send a notification.');
        }

        $this->notifiable = $this->directNotifiable ?? $this->notifiable;

        return $prepped->bootByNotification($this->notifiable, $this->notification);
    }

    /**
     * Fill in anything the callback left unset from the properties.
     *
     * @return SwissechoMessage
     */
    private function builderForDirectSend(): SwissechoMessage
    {
        //The callback wins; these only fill the gaps it left.
        if(!$this->echoBuilderMessage->to && $this->to) {
            $this->echoBuilderMessage->to($this->to);
        }

        if(!$this->echoBuilderMessage->sender && $this->sender) {
            $this->echoBuilderMessage->sender($this->sender);
        }

        return $this->echoBuilderMessage;
    }

    /**
     * @throws SwissechoException
     */
    public function quick($phoneNumber, $message): ?SendResult
    {
        //get default route
        return $this->route(null, function(SwissechoMessage $ms) use ($phoneNumber, $message) {
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
