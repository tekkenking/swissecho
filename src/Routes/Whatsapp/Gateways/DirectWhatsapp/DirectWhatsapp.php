<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp\Gateways\DirectWhatsapp;

use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\BaseGateway;
use Tekkenking\Swissecho\SwissechoException;

/**
 * Sends through the WhatsApp Cloud API, Meta's own endpoint, with no
 * reseller in between. Everything it needs comes from this gateway's
 * config block.
 *
 * Note the Cloud API accepts one recipient per request, so boot() is
 * overridden to make one request per recipient rather than silently
 * dropping all but the first.
 */
class DirectWhatsapp extends BaseGateway
{
    /**
     * The message body for one recipient.
     *
     * @return array
     * @throws SwissechoException
     */
    public function init(): mixed
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->currentRecipient,
        ];

        $template = $this->templateConfig();

        if($template !== null) {
            return $payload + [
                'type'     => 'template',
                'template' => $template,
            ];
        }

        return $payload + [
            'type' => 'text',
            'text' => [
                'preview_url' => (bool) ($this->config['preview_url'] ?? false),
                'body'        => (string) $this->body,
            ],
        ];
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
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer '.$this->accessToken(),
                'Content-Type: application/json',
            ],
        ]);

        return $curl;
    }

    /**
     * Where to POST.
     *
     * A 'url' in the config is used as-is, for a proxy or a pinned
     * version. Otherwise it is built from the Graph API version and the
     * phone number id, which is the documented shape:
     * https://graph.facebook.com/{version}/{phone-number-id}/messages
     *
     * @return string
     * @throws SwissechoException
     */
    protected function endpoint(): string
    {
        if(!empty($this->config['url'])) {
            return (string) $this->config['url'];
        }

        $phoneNumberId = $this->config['phone_number_id'] ?? null;

        if(empty($phoneNumberId)) {
            throw new SwissechoException(
                'Whatsapp: set WHATSAPP_PHONE_NUMBER_ID, or a full url on the directwhatsapp gateway.'
            );
        }

        $version = trim((string) ($this->config['api_version'] ?? 'v21.0'), '/');
        $host = rtrim((string) ($this->config['api_host'] ?? 'https://graph.facebook.com'), '/');

        return $host.'/'.$version.'/'.$phoneNumberId.'/messages';
    }

    /**
     * @return string
     * @throws SwissechoException
     */
    protected function accessToken(): string
    {
        $token = $this->config['auth']['access_token'] ?? null;

        if(empty($token)) {
            throw new SwissechoException('Whatsapp: set WHATSAPP_ACCESS_TOKEN for the directwhatsapp gateway.');
        }

        return (string) $token;
    }

    /**
     * The template block, when one is configured.
     *
     * Free-form text only reaches a user inside the 24 hour customer
     * service window; outside it WhatsApp requires an approved template.
     * Configure one and every send uses it, with the message body passed
     * as the single body parameter.
     *
     * @return array|null
     */
    protected function templateConfig(): ?array
    {
        $name = $this->config['template']['name'] ?? null;

        if(empty($name)) {
            return null;
        }

        return [
            'name'     => (string) $name,
            'language' => ['code' => (string) ($this->config['template']['language'] ?? 'en_US')],
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $this->body],
                    ],
                ],
            ],
        ];
    }
}
