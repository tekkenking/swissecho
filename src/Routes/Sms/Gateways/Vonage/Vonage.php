<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Vonage;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;
use Tekkenking\Swissecho\SendsPerRecipient;
use Tekkenking\Swissecho\SwissechoException;

/**
 * Vonage SMS API (formerly Nexmo).
 *
 * One request per recipient. Vonage answers HTTP 200 whether or not it
 * accepted the message, so success is read from the body.
 *
 * https://developer.vonage.com/en/api/sms
 */
class Vonage extends BaseGateway
{
    use SendsPerRecipient;

    /**
     * @return array<string, string>
     * @throws SwissechoException
     */
    public function init(): mixed
    {
        return [
            'api_key'    => $this->credential('api_key', 'VONAGE_API_KEY'),
            'api_secret' => $this->credential('api_secret', 'VONAGE_API_SECRET'),
            'to'         => ltrim((string) $this->currentRecipient, '+'),
            'from'       => $this->from(),
            'text'       => (string) $this->body,
        ];
    }

    /**
     * @param $data
     * @return \CurlHandle
     */
    public function send($data): \CurlHandle
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => ($this->config['url'] ?? null) ?: 'https://rest.nexmo.com/sms/json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        return $curl;
    }

    /**
     * The sender, resolved the way every gateway's is: the one set on the
     * message, then the notifiable's own, then this gateway's `sender`
     * (VONAGE_FROM), then SWISSECHO_SENDER. A sender chosen for this
     * message always wins over the configured default.
     *
     * @throws SwissechoException
     */
    protected function from(): string
    {
        if($this->sender === '') {
            throw new SwissechoException('Vonage: set VONAGE_FROM, or ->sender() on the message.');
        }

        return $this->sender;
    }

    /**
     * @throws SwissechoException
     */
    protected function credential(string $key, string $env): string
    {
        if(empty($this->config['auth'][$key])) {
            throw new SwissechoException('Vonage: set '.$env.' for the vonage gateway.');
        }

        return (string) $this->config['auth'][$key];
    }

    /**
     * Each entry in `messages` carries a status: "0" is accepted, with a
     * message-id, and anything else is a rejection explained in error-text.
     * The HTTP status is 200 either way.
     * https://developer.vonage.com/en/messaging/sms/guides/troubleshooting-sms
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $data = $this->decodeJson($body);
        $messages = is_array($data['messages'] ?? null) ? $data['messages'] : null;

        if($messages === null || $messages === []) {
            return parent::interpretResponse($body, $httpCode);
        }

        $messageIds = [];

        foreach ($messages as $message) {
            if((string) ($message['status'] ?? '') !== '0') {
                return [
                    'success'       => false,
                    'error_code'    => (string) ($message['status'] ?? ''),
                    'error_message' => (string) ($message['error-text'] ?? 'Vonage rejected the message'),
                ];
            }

            $messageIds[] = $message['message-id'] ?? null;
        }

        return ['success' => true, 'message_id' => implode(',', array_filter($messageIds)) ?: null];
    }
}
