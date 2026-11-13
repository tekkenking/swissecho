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
     * The recipient the current request is for.
     *
     * @var string|null
     */
    protected ?string $currentRecipient = null;

    /**
     * One request per recipient.
     *
     * @return self
     * @throws SwissechoException
     */
    public function boot(): self
    {
        $this->swissecho_config = config('swissecho');
        $this->processDependencies();

        //Captured before the loop, because the loop replaces $this->to
        //with the recipient of each individual request.
        $recipients = $this->recipients();

        if($recipients === []) {
            throw new SwissechoException('Whatsapp: no recipient to send to.');
        }

        foreach ($recipients as $recipient) {
            $this->currentRecipient = $recipient;

            //So the AfterSend event for each request names the number that
            //request actually went to, rather than the whole list.
            $this->to = $recipient;

            $this->requestPayload = $this->init();
            $ch = $this->send($this->requestPayload);
            $this->execCurl($ch ?? null);
        }

        return $this;
    }

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

    /**
     * Every recipient, trimmed, with blanks dropped.
     *
     * @return array
     */
    protected function recipients(): array
    {
        //Read from $this->to, not $this->payload: the trait declares
        //$payload private, so it belongs to BaseGateway and is not
        //accessible from a subclass.
        $list = is_array($this->to) ? $this->to : [$this->to];

        $clean = [];

        foreach ($list as $entry) {
            //A comma-separated string is a documented input to to(), and
            //BaseRoute::pushToGateway() normally splits it before we get
            //here. Split again so the gateway is also correct when it is
            //constructed directly.
            foreach (explode(',', (string) $entry) as $number) {
                $number = trim($number);

                if($number !== '') {
                    $clean[] = $number;
                }
            }
        }

        return $clean;
    }
}
