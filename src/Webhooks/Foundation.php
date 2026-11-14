<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tekkenking\Swissecho\Events\WebhookReceived;

class Foundation {

    protected mixed $config;

    /**
     * Receive a provider callback.
     *
     * Route parameters are read by name rather than taken as method
     * arguments: the route declares three of them and Laravel binds
     * non-class arguments positionally, so a signature of
     * (string $type, string $key, Request $request) received the gateway
     * name as $key and discarded the secret entirely.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $route   = (string) $request->route('route');
        $gateway = (string) $request->route('class');
        $secret  = (string) $request->route('secret');

        $webhook = $this->webhookConfig($route, $gateway);

        if($webhook === null) {
            return response()->json(['message' => 'no webhook setup'], 404);
        }

        $configuredSecret = $webhook['secret'] ?? null;

        if (empty($configuredSecret)) {
            return response()->json(['message' => 'webhook not configured'], 401);
        }

        if(!$this->validateSecret($secret, $configuredSecret)) {
            return response()->json(['message' => 'invalid key'], 401);
        }

        $this->dispatchCallback($route, $gateway, $webhook, $request);

        return response()->json(['message' => 'Webhook successful']);
    }

    /**
     * Read the webhook block for one gateway.
     *
     * The block lives at routes_options.{route}.gateway_options.{gateway}.webhook,
     * which is where the config defines it and what the README documents. The
     * previous lookup read routes_options.{route}.webhook, a key that does not
     * exist, so every callback was answered with a 404.
     *
     * @param string $route
     * @param string $gateway
     * @return array|null
     */
    protected function webhookConfig(string $route, string $gateway): ?array
    {
        $this->config = $this->loadConfig();

        $webhook = $this->config['routes_options'][$route]['gateway_options'][$gateway]['webhook'] ?? null;

        return is_array($webhook) ? $webhook : null;
    }

    /**
     * Hand the callback to the configured class, or announce it as an event.
     *
     * @param string $route
     * @param string $gateway
     * @param array $webhook
     * @param Request $request
     * @return void
     */
    protected function dispatchCallback(string $route, string $gateway, array $webhook, Request $request): void
    {
        $handlerClass = $webhook['class'] ?? null;
        $handlerMethod = $webhook['handle'] ?? 'handle';

        if($handlerClass && class_exists($handlerClass) && method_exists($handlerClass, $handlerMethod)) {
            $this->callEventHook($handlerClass, $handlerMethod, $request);
            return;
        }

        WebhookReceived::dispatch(
            $route,
            $gateway,
            $request->all(),
            $request->headers->all()
        );
    }

    public function loadConfig()
    {
        return config("swissecho");
    }

    private function validateSecret(string $key, ?string $routeWebhookSecret): bool
    {
        return $routeWebhookSecret !== null && hash_equals($routeWebhookSecret, $key);
    }

    protected function callEventHook($routeWehookClass, $routeWebhookHandle, $request): void
    {
        (new $routeWehookClass)->$routeWebhookHandle($request);
    }


}
