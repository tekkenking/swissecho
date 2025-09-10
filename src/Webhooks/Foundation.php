<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Foundation {

    protected mixed $config;

    /**
     * Summary of a handle
     * @param string $type
     * @param string $key
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(string $type, string $key, Request $request): JsonResponse
    {

        //First loaded the config
        $this->config = $this->loadConfig();

        //Select the route option and the route itself
        $routes_options = $this->config['routes_options'];
        if(!isset($routes_options[$type])) {
            return response()->json(['message'  => 'not found'],404);
        }
        $route = $routes_options[$type];

        //Check if the route has webhook setup
        if(!isset($route['webhook'])) {
            return response()->json(['message'  => 'no webhook setup'],404);
        }


        $routeWebhookSecret = $route['webhook']['secret'];
        $routeWehookClass = $route['class'];
        $routeWebhookHandle = $route['webhook']['handle'];

        //Validate the secret key from the webhook url
        if(!$this->validateSecret($key, $routeWebhookSecret)) {
            //Invalid key;
            return response()->json(['message'=> 'invalid key']);
        }

        //initiate the webhook class and method of the route class
        $this->callEventHook($routeWehookClass, $routeWebhookHandle, $request);

        return response()->json(['message'=> 'Webhook successful']);

    }

    private function validateSecret(string $key, string $routeWebhookSecret): bool
    {
        return ($key === $routeWebhookSecret);
    }

    public function loadConfig()
    {
        return config("swissecho");
    }

    protected function callEventHook($routeWehookClass, $routeWebhookHandle, $request): void
    {
        (new $routeWehookClass)->$routeWebhookHandle($request);
    }


}
