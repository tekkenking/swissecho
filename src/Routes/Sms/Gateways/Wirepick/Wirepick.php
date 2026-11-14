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
        $affiliate = $this->config['affiliate'] ?? $this->config['affliate'] ?? null;

        $mobiles = implode(',', $this->to);

        $data = $url.'?'.http_build_query([
            'client'    => $client,
            'password'  => $password,
            'affiliate' => $affiliate,
            'phone'     => $mobiles,
            'text'      => $this->body,
            'from'      => $this->sender,
        ]);

        return $data;
    }

    public function send($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        return $ch;
    }
}
