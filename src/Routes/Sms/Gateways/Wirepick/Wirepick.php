<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Wirepick;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class Wirepick extends BaseGateway
{

    /**
     * @inheritDoc
     */
    public function init(): string
    {
        $url = $this->config['url'];
        $client = $this->config['client'];
        $password = $this->config['password'];
        $affiliate = $this->config['affliate'];

        $this->body = urlencode($this->body);
        $mobiles = implode(',', $this->to);

        $data = $url."?client=".$client."&password=".$password."&affiliate=".$affiliate."&phone=".$mobiles."&text=".$this->body."&from=".$this->sender;

        return $data;
    }

    public function send($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        return $ch;
    }
}
