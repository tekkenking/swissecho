<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp\Gateways\Kudisms;

use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\BaseGateway;
use Tekkenking\Swissecho\SwissechoException;

class KudismsWhatsapp extends BaseGateway
{

    /**
     * One message for the recipient the base class is currently on.
     *
     * @return array
     * @throws SwissechoException
     */
    public function init(): array
    {
        return [
            'token'         => $this->config['auth']['api_key'] ?? null,
            'template_code' => $this->templateCode(),
            'recipient'     => $this->currentRecipient,
            'parameters'    => $this->body,
        ];
    }

    /**
     * The template registered on the Kudisms account.
     *
     * This used to be the literal '2147483647', a placeholder no account
     * would ever have, so every WhatsApp send through Kudisms was rejected -
     * and before the HTTP status was checked, that rejection was reported
     * back as a successful send.
     *
     * @return string
     * @throws SwissechoException
     */
    protected function templateCode(): string
    {
        $templateCode = $this->config['template_code'] ?? null;

        if(empty($templateCode)) {
            throw new SwissechoException(
                'Whatsapp: set KUDISMS_TEMPLATE_CODE to the template code registered on your Kudisms account.'
            );
        }

        return (string) $templateCode;
    }

    /**
     * @param $data
     * @return \CurlHandle|bool
     */
    public function send($data): \CurlHandle|bool
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($data), // encodes it correctly
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded"
            ),
        ));

        return $curl;
    }

    /**
     * KudiSMS puts an error_code in every JSON reply: "000" is accepted,
     * with "recipient|message-id" in `data`, and anything else is a
     * rejection explained in `msg`. Used only when that field is present.
     * https://kudisms.net/docs/errors/
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $data = $this->decodeJson($body);

        if($data === null || !isset($data['error_code'])) {
            return parent::interpretResponse($body, $httpCode);
        }

        if((string) $data['error_code'] !== '000') {
            return [
                'success'       => false,
                'error_code'    => (string) $data['error_code'],
                'error_message' => isset($data['msg']) ? (string) $data['msg'] : 'KudiSMS rejected the message',
            ];
        }

        $parts = is_string($data['data'] ?? null) ? explode('|', $data['data']) : [];

        return ['success' => true, 'message_id' => $parts[1] ?? null];
    }
}
