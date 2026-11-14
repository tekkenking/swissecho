<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes\Slack;

use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\Routes\HttpRoute;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;

class SlackRoute extends BaseRoute
{
    use HttpRoute;

    protected array $requestPayload = [];

    /**
     * Slack has no gateways to switch between.
     *
     * Naming one used to be accepted and ignored, while every other route
     * raises a clear exception for a gateway it has no config for.
     */
    public function gateway(string|null $gateway = null): static
    {
        if($gateway !== null && $gateway !== '') {
            throw new SwissechoException(
                'Slack: the slack route has no gateways. Remove ->gateway(\''.$gateway.'\') - '
                . 'configure SLACK_WEBHOOK_URL or SLACK_BOT_TOKEN instead.'
            );
        }

        return $this;
    }

    /**
     * Resolve the recipient channel/user for Slack.
     * Priority: msgBuilder->to → routeNotificationSlackChannel() → config default_channel
     */
    protected function resolveRecipient(): mixed
    {
        if ($this->msgBuilder->to) {
            return $this->msgBuilder->to;
        }

        if (isset($this->notifiable) && method_exists($this->notifiable, 'routeNotificationSlackChannel')) {
            return $this->notifiable->routeNotificationSlackChannel();
        }

        return $this->config['routes_options']['slack']['default_channel'] ?? null;
    }

    /**
     * Called when the channel is dispatched via Laravel's notification system.
     */
    public function sendViaNotification(): static
    {
        if (!method_exists($this->notification, 'toSlack')) {
            throw new SwissechoException('Slack notification: toSlack() method missing');
        }

        $msgBuilder = $this->notification->toSlack($this->notifiable);

        if (!$msgBuilder instanceof SwissechoMessage) {
            throw new SwissechoException('Slack notification: toSlack() must return a SwissechoMessage instance');
        }

        $this->msgBuilder = $msgBuilder;
        $this->msgBuilder->to($this->resolveRecipient());

        $this->send();

        return $this;
    }

    /**
     * Called when sending directly via swissecho()->route('slack')->go().
     */
    public function directSend($routeBuilder): static
    {
        $this->msgBuilder = $routeBuilder;
        $this->msgBuilder->to($this->resolveRecipient());

        $this->send();

        return $this;
    }

    /**
     * Core send logic shared by both notification and direct modes.
     */
    protected function send(): void
    {
        $this->ensureHttpRouteAndGateway('slack', 'slack');
        $this->setIdentifier();

        $message = $this->msgBuilder->message;
        if (!$message) {
            throw new SwissechoException('Slack: message body is required');
        }

        //Resolved before the mock branch, so a misconfiguration surfaces in
        //development instead of only in production. The channel check used
        //to sit inside postMessage(), past the mock, so a bot token with no
        //channel reported success locally and threw live.
        $credential = $this->resolveCredential();

        //Set here, not in postMessage(), so the mock path reports the same
        //recipient the live path would.
        if ($credential['mode'] === 'webhook') {
            $this->reportsRequestedRecipient = false;
        }

        if (!($this->config['live'] ?? false)) {
            $this->handleHttpMock();
            return;
        }

        $this->dispatchAfterSendEvent($this->postMessage($message, $credential));
    }

    /**
     * Post through whichever credential is configured.
     *
     * @param  string  $message
     * @return array
     * @throws SwissechoException
     */
    protected function postMessage(string $message, array $credential): array
    {
        if ($credential['mode'] === 'webhook') {
            //A webhook posts only to the channel it was created for, so no
            //channel is sent. Reported as such rather than echoing a channel
            //that was never used.
            $this->requestPayload = ['text' => $message];

            return $this->postJson($credential['url'], $this->requestPayload, [
                'Content-Type: application/json',
            ]);
        }

        $this->requestPayload = [
            'channel' => $credential['channel'],
            'text'    => $message,
        ];

        return $this->postJson($credential['url'], $this->requestPayload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $credential['token'],
        ]);
    }

    /**
     * Decide which credential this send uses, and fail early if it cannot.
     *
     * When both are configured the bot token is preferred if a channel was
     * asked for, because a webhook cannot honour one: ->to('#alerts') used
     * to be accepted and then silently dropped into the webhook's own
     * channel, while the event reported the channel as delivered.
     *
     * @return array
     * @throws SwissechoException
     */
    protected function resolveCredential(): array
    {
        $routeConfig = $this->config['routes_options']['slack'] ?? [];
        $webhookUrl  = $routeConfig['auth']['webhook'] ?? null;
        $token       = $routeConfig['auth']['token'] ?? null;
        $channel     = $this->msgBuilder->to ?: ($routeConfig['default_channel'] ?? null);

        if ($token && $channel) {
            return [
                'mode'    => 'token',
                'token'   => $token,
                'channel' => $channel,
                'url'     => $routeConfig['url'] ?? 'https://slack.com/api/chat.postMessage',
            ];
        }

        if ($webhookUrl) {
            return ['mode' => 'webhook', 'url' => $webhookUrl];
        }

        if ($token) {
            throw new SwissechoException(
                'Slack: a channel is required with a bot token. Set ->to(\'#channel\'), '
                . 'or SLACK_DEFAULT_CHANNEL, or configure SLACK_WEBHOOK_URL instead.'
            );
        }

        throw new SwissechoException('Slack: configure SLACK_WEBHOOK_URL or SLACK_BOT_TOKEN');
    }
}
