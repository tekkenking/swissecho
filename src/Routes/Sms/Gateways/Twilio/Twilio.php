<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Twilio;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;
use Tekkenking\Swissecho\SendsPerRecipient;
use Tekkenking\Swissecho\SwissechoException;

/**
 * Twilio Programmable Messaging.
 *
 * One request per recipient, because the Messages resource takes a single
 * To. Authentication is the Account SID and Auth Token over HTTP Basic, so
 * no credential appears in the request payload.
 *
 * https://www.twilio.com/docs/messaging/api/message-resource
 */
class Twilio extends BaseGateway
{
    use SendsPerRecipient;

    /**
     * @return array<string, string>
     * @throws SwissechoException
     */
    public function init(): mixed
    {
        //Twilio wants E.164 with the plus; recipients arrive as digits.
        return [
            'To'   => '+'.ltrim((string) $this->currentRecipient, '+'),
            'Body' => (string) $this->body,
        ] + $this->origin();
    }

    /**
     * @param $data
     * @return \CurlHandle
     * @throws SwissechoException
     */
    public function send($data): \CurlHandle
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->endpoint(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->accountSid().':'.$this->authToken(),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        return $curl;
    }

    /**
     * Who the message is from.
     *
     * $this->sender has already been resolved the way every gateway's is:
     * the sender set on the message, then the notifiable's own, then this
     * gateway's `sender` (TWILIO_FROM), then SWISSECHO_SENDER. So a sender
     * chosen for this message - a tenant's business name on a shared
     * platform, say - always wins over the configured default.
     *
     * A Messaging Service picks the number itself, so it stands in for the
     * configured default only: it is used when nothing chose a sender for
     * this message.
     *
     * @return array<string, string>
     * @throws SwissechoException
     */
    protected function origin(): array
    {
        if(!empty($this->config['messaging_service_sid']) && !$this->senderWasChosenForThisMessage()) {
            return ['MessagingServiceSid' => (string) $this->config['messaging_service_sid']];
        }

        if($this->sender === '') {
            throw new SwissechoException(
                'Twilio: set TWILIO_FROM or TWILIO_MESSAGING_SERVICE_SID, or ->sender() on the message.'
            );
        }

        return ['From' => $this->sender];
    }

    /**
     * True when the sender is not simply the configured fallback.
     */
    protected function senderWasChosenForThisMessage(): bool
    {
        $fallback = (string) (($this->config['sender'] ?? null) ?: config('swissecho.sender'));

        return $this->sender !== '' && $this->sender !== $fallback;
    }

    /**
     * @throws SwissechoException
     */
    protected function endpoint(): string
    {
        if(!empty($this->config['url'])) {
            return (string) $this->config['url'];
        }

        return 'https://api.twilio.com/2010-04-01/Accounts/'.$this->accountSid().'/Messages.json';
    }

    /**
     * @throws SwissechoException
     */
    protected function accountSid(): string
    {
        if(empty($this->config['account_sid'])) {
            throw new SwissechoException('Twilio: set TWILIO_ACCOUNT_SID for the twilio gateway.');
        }

        return (string) $this->config['account_sid'];
    }

    /**
     * @throws SwissechoException
     */
    protected function authToken(): string
    {
        if(empty($this->config['auth']['token'])) {
            throw new SwissechoException('Twilio: set TWILIO_AUTH_TOKEN for the twilio gateway.');
        }

        return (string) $this->config['auth']['token'];
    }

    /**
     * Twilio uses HTTP status properly: 201 with the message's `sid` when
     * accepted, and a 4xx or 5xx with `code` and `message` when not.
     * https://www.twilio.com/docs/usage/twilios-response
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $verdict = parent::interpretResponse($body, $httpCode);
        $data = $this->decodeJson($body);

        if($data === null) {
            return $verdict;
        }

        if(!$verdict['success']) {
            return [
                'success'       => false,
                'error_code'    => isset($data['code']) ? (string) $data['code'] : $verdict['error_code'],
                'error_message' => isset($data['message']) ? (string) $data['message'] : $verdict['error_message'],
            ];
        }

        return ['success' => true, 'message_id' => $data['sid'] ?? null];
    }
}
