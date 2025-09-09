<?php

namespace Tekkenking\Swissecho\Routes\Whatsapp\Gateways\Kudisms;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class KudismsWhatsapp extends BaseGateway
{

    /**
     * @return string
     */
    public function init(): array
    {
        $data = [
            "token"             => $this->config['auth']['api_key'],
            "template_code"     => '2147483647',
            "recipient"         => $this->to[0],
            //"from"      => $this->sender,
            "parameters"        => $this->body,
        ];

        return $data;
    }

    public function send($data): \CurlHandle|bool
    {
        //dd($data);
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
            CURLOPT_POSTFIELDS => http_build_query($data), // encodes it correctly
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded"
            ),
        ));

        return $curl;
    }


}
