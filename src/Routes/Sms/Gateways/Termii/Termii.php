<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Termii;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class Termii extends BaseGateway
{

    /**
     * @return string
     */
    public function init(): string
    {
        $data = [
            "api_key"   => $this->config['auth']['api_key'],
            "channel"   => $this->config['channel'],
            "to"        => $this->to,
            "from"      => $this->sender,
            "sms"       => $this->body,
            "type"      => "plain"
        ];

        return json_encode($data);
    }

    public function send($data): \CurlHandle|bool
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        return $curl;
    }


}
