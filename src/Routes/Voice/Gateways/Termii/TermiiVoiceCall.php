<?php

namespace Tekkenking\Swissecho\Routes\Voice\Gateways\Termii;

use Tekkenking\Swissecho\Routes\Voice\Gateways\BaseGateway;

class TermiiVoiceCall extends BaseGateway
{

    /**
     * @return array
     */
    public function init(): array
    {
        $data = [
            "api_key"   => $this->config['auth']['api_key'],
            "phone_number"        => $this->to,
            "code"       => $this->body
        ];

        return $data;
    }

    public function send($data): \CurlHandle|bool
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => $data['code'],
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        return $curl;
    }

}
