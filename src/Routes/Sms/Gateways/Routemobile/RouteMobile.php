<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Routemobile;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class RouteMobile extends BaseGateway
{

    /**
     * @return string
     */
    public function init(): string
    {
        $pd = [
            'url'           =>  $this->config['url'],
            'type'          => 0,
            'dlr'           => 0,
            'message'       => urlencode($this->body),
            'destination'   => implode(urlencode(','),$this->to),
            'source'        => $this->sender,
            'username'      => $this->config['auth']['username'],
            'password'      => $this->config['auth']['password']
        ];


        $url = $pd['url'].'?';
        $url .= "username={$pd['username']}";
        $url .= "&password={$pd['password']}";
        $url .= "&type={$pd['type']}";
        $url .= "&source={$pd['source']}";
        $url .= "&destination={$pd['destination']}";
        $url .= "&dlr={$pd['dlr']}";
        $url .= "&message={$pd['message']}";

        return $url;
    }

    public function send($data): \CurlHandle|bool
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        return $ch;
    }

}
