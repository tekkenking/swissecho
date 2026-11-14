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
        if($gateway !== null && $gateway !== '') {
            throw new SwissechoException(
                'Telegram: the telegram route has no gateways. Remove ->gateway(\''.$gateway.'\') - '
                . 'configure TELEGRAM_BOT_TOKEN instead.'
            );
        }

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

        //Built before the mock branch, so a mocked send carries the same
        //insightPayload['request'] a live one would. It used to stay [] for
        //this route, leaving anything auditing mock sends with nothing.
        $this->requestPayload = $this->buildPayload($chatId, $message);

        //Mock before the token check, as SlackRoute does. Requiring a bot
        //token in order to produce a mock log meant local development
        //without one threw, while the same setup mocked fine on Slack.
        if (!($this->config['live'] ?? false)) {
            $this->handleHttpMock();
            return;
        }

        $this->dispatchAfterSendEvent($this->postMessage());
    }

    /**
     * Post to the Bot API.
     *
     * @param  mixed  $chatId
     * @param  string  $message
     * @return array
     * @throws SwissechoException
     */
    protected function buildPayload($chatId, string $message): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text'    => $message,
        ];

        $parseMode = $this->config['routes_options']['telegram']['parse_mode'] ?? null;

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        return $payload;
    }

    /**
     * Post the already-built payload to the Bot API.
     *
     * @return array
     * @throws SwissechoException
     */
    protected function postMessage(): array
    {
        $routeConfig = $this->config['routes_options']['telegram'] ?? [];
        $token       = $routeConfig['auth']['token'] ?? null;

        if (!$token) {
            throw new SwissechoException('Telegram: configure TELEGRAM_BOT_TOKEN');
        }

        return $this->postJson(
            $routeConfig['url'] ?? ('https://api.telegram.org/bot' . $token . '/sendMessage'),
            $this->requestPayload,
            ['Content-Type: application/json']
        );
    }
}
