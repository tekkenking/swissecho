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
        $encodedBody = urlencode($this->body);

        //Preparing post parameters
        $pd = [
            'username'  => $username,
            'password'  => $password,
            'message'   => $encodedBody,
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
        curl_setopt($ch, CURLOPT_HEADER, false);

        return $ch;
    }

    /**
     * NigeriaBulkSMS reports a rejection inside the body, as
     * {"error":"Login denied.","errno":"103"}, and a success as
     * {"status":"OK","count":1,"price":8}. It returns no message ID.
     * https://nigeriabulksms.com/sms-gateway-api-in-nigeria/
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $data = $this->decodeJson($body);

        if($data !== null && isset($data['error'])) {
            return [
                'success'       => false,
                'error_code'    => isset($data['errno']) ? (string) $data['errno'] : null,
                'error_message' => (string) $data['error'],
            ];
        }

        return parent::interpretResponse($body, $httpCode);
    }
}
