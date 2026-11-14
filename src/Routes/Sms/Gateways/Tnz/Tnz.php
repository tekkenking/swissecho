<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class Tnz extends BaseGateway
{
    private $authToken;

    private $url;

    /**
     * @inheritDoc
     */
    public function init(): mixed
    {
        $this->url          = $this->config['url'];
        $this->authToken    = $this->config['auth']['api_key'];

        $recipientsArr = [];
        foreach ($this->to as $num) {
            $recipientsArr[] = ["Recipient" => $num];
        }

        $data = [
            "MessageData" => [
                "Message" => $this->body,
                "sender"    =>  $this->sender,
                "Destinations" => $recipientsArr,
            ]
        ];

        return json_encode($data);
    }

    public function send($data)
    {
        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "encoding='utf-8'",
            "Content-length: ".strlen($data),
            "Authorization: Basic {$this->authToken}",
            "Connection: close"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    /**
     * Only the markers that hold across TNZ's API versions: a Result of
     * Failed or Unauthorized is a rejection with its ErrorMessage, which
     * may be a string or a list, and MessageID identifies an accepted
     * message. Anything else is judged by HTTP status.
     * https://www.tnz.co.nz/docs/restapi/
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $verdict = parent::interpretResponse($body, $httpCode);
        $data = $this->decodeJson($body);

        if($data === null) {
            return $verdict;
        }

        if(in_array($data['Result'] ?? null, ['Failed', 'Unauthorized'], true)) {
            $reason = $data['ErrorMessage'] ?? $data['Result'];

            return [
                'success'       => false,
                'error_code'    => $verdict['error_code'] ?? null,
                'error_message' => is_array($reason) ? implode('; ', $reason) : (string) $reason,
            ];
        }

        return $verdict + ['message_id' => $data['MessageID'] ?? null];
    }
}
