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
     * Slack does not use per-gateway switching; override to be a no-op.
     */
    public function gateway(string|null $gateway = null): static
    {
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

        if (!($this->config['live'] ?? false)) {
            $this->handleHttpMock();
            return;
        }

        $routeConfig = $this->config['routes_options']['slack'] ?? [];
        $webhookUrl  = $routeConfig['auth']['webhook'] ?? null;
        $token       = $routeConfig['auth']['token'] ?? null;
        $apiUrl      = $routeConfig['url'] ?? 'https://slack.com/api/chat.postMessage';

        if ($webhookUrl) {
            // Incoming Webhook mode — recipient is embedded in the webhook URL.
            $this->requestPayload = ['text' => $message];
            $response = $this->postJson($webhookUrl, $this->requestPayload, [
                'Content-Type: application/json',
            ]);
        } elseif ($token) {
            // Bot Token mode — requires a channel.
            $recipient = $this->msgBuilder->to;
            if (!$recipient) {
                throw new SwissechoException('Slack: recipient channel is required when using a bot token');
            }

            $this->requestPayload = [
                'channel' => $recipient,
                'text'    => $message,
            ];
            $response = $this->postJson($apiUrl, $this->requestPayload, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ]);
        } else {
            throw new SwissechoException(
                'Slack: configure SLACK_WEBHOOK_URL or SLACK_BOT_TOKEN'
            );
        }

        $this->dispatchAfterSendEvent($response);
    }
}
