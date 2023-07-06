<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Smsbroadcast;

use Illuminate\Support\Str;

class SmsBroadCastDotComDotAu extends \Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway
{

    /**
     * @inheritDoc
     */
    public function init(): mixed
    {

        $username = $this->config['auth']['username'];
        $password = $this->config['auth']['password'];
        $destination = implode(',', $this->to);

        $source = $this->from;
        $text = $this->body;
        $ref = Str::random(10);

        $content =  'username='.rawurlencode($username).
            '&password='.rawurlencode($password).
            '&to='.rawurlencode($destination).
            '&from='.rawurlencode($source).
            '&message='.rawurlencode($text).
            '&ref='.rawurlencode($ref);

        return $content;
    }

    public function send($data)
    {
        $ch = curl_init($this->config['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }
}
