<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Nigerianbulksms;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class Nigerianbulksms extends BaseGateway
{

    /**
     * @inheritDoc
     */
    public function init(): mixed
    {
        $username = $this->config['auth']['username'];
        $password = $this->config['auth']['password'];
        $this->body = urlencode($this->body);

        //Preparing post parameters
        $pd = [
            'username'  => $username,
            'password'  => $password,
            'message'   => $this->body,
            'sender'    => $this->sender,
            'mobiles'   => str_replace('+', '', implode(',',$this->to)),
            'verbose'   =>  'true'
        ];

        $url = $this->config['url']."?username={$pd['username']}&password={$pd['password']}&message={$pd['message']}&sender={$pd['sender']}&mobiles={$pd['mobiles']}&verbose={$pd['verbose']}";

        return $url;
    }

    public function send($data)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        return $ch;
    }
}
