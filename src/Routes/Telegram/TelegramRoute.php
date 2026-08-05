<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes\Telegram;

use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\Routes\HttpRoute;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;

class TelegramRoute extends BaseRoute
{
    use HttpRoute;

    protected array $requestPayload = [];

    /**
     * Telegram does not use per-gateway switching; override to be a no-op.
     */
    public function gateway(string|null $gateway = null): static
    {
        return $this;
    }

    /**
     * Resolve the chat_id for Telegram.
     * Priority: msgBuilder->to → routeNotificationTelegramChatId() → config default_chat_id
     */
    protected function resolveRecipient(): mixed
    {
        if ($this->msgBuilder->to) {
            return $this->msgBuilder->to;
        }

        if (isset($this->notifiable) && method_exists($this->notifiable, 'routeNotificationTelegramChatId')) {
            return $this->notifiable->routeNotificationTelegramChatId();
        }

        return $this->config['routes_options']['telegram']['default_chat_id'] ?? null;
    }

    /**
     * Called when the channel is dispatched via Laravel's notification system.
     */
    public function sendViaNotification(): static
    {
        if (!method_exists($this->notification, 'toTelegram')) {
            throw new SwissechoException('Telegram notification: toTelegram() method missing');
        }

        $msgBuilder = $this->notification->toTelegram($this->notifiable);

        if (!$msgBuilder instanceof SwissechoMessage) {
            throw new SwissechoException('Telegram notification: toTelegram() must return a SwissechoMessage instance');
        }

        $this->msgBuilder = $msgBuilder;
        $this->msgBuilder->to($this->resolveRecipient());

        $this->send();

        return $this;
    }

    /**
     * Called when sending directly via swissecho()->route('telegram')->go().
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
        $this->ensureHttpRouteAndGateway('telegram', 'telegram');
        $this->setIdentifier();

        $chatId = $this->msgBuilder->to;
        if (!$chatId) {
            throw new SwissechoException('Telegram: chat_id (recipient) is required');
        }

        $message = $this->msgBuilder->message;
        if (!$message) {
            throw new SwissechoException('Telegram: message body is required');
        }

        $routeConfig = $this->config['routes_options']['telegram'] ?? [];
        $token       = $routeConfig['auth']['token'] ?? null;

        if (!$token) {
            throw new SwissechoException('Telegram: configure TELEGRAM_BOT_TOKEN');
        }

        if (!($this->config['live'] ?? false)) {
            $this->handleHttpMock();
            return;
        }

        $apiUrl = $routeConfig['url']
            ?? ('https://api.telegram.org/bot' . $token . '/sendMessage');

        $this->requestPayload = [
            'chat_id' => $chatId,
            'text'    => $message,
        ];

        $parseMode = $routeConfig['parse_mode'] ?? null;
        if ($parseMode) {
            $this->requestPayload['parse_mode'] = $parseMode;
        }

        $response = $this->postJson($apiUrl, $this->requestPayload, [
            'Content-Type: application/json',
        ]);

        $this->dispatchAfterSendEvent($response);
    }
}
