<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz;

class Tnz extends \Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }
}
