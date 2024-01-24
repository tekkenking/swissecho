<?php

namespace Tekkenking\Swissecho\Routes\Voice\Gateways\Textngxyz;

use Tekkenking\Swissecho\Routes\Voice\Gateways\BaseGateway;

class TextngxyzVoiceCall extends BaseGateway
{

    /**
     * @return array
     */
    public function init(): array
    {
        $data = [
            'key'                   =>  $this->config['auth']['api_key'],
            'phone'                 =>  $this->to,
            'message-opt-code'      =>  $this->body,
            'otp_repeat'            =>  $this->config['repeat_times'],
            'custom_ref'            =>  rand()
        ];

        return $data;
    }

    public function send($data): \CurlHandle
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        return $curl;
    }

}
