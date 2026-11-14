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
        curl_setopt($ch, CURLOPT_HEADER, 0);
        return $ch;
    }

    /**
     * What each RouteMobile reply code means. The reply carries no text.
     * https://routemobile.com/pdf/SmsPlus_BulkHttp.pdf
     */
    private const REPLY_CODES = [
        '1702' => 'Invalid URL or a missing parameter',
        '1703' => 'Invalid username or password',
        '1704' => 'Invalid message type',
        '1705' => 'Invalid message',
        '1706' => 'Invalid destination',
        '1707' => 'Invalid source (sender)',
        '1708' => 'Invalid dlr value',
        '1709' => 'User validation failed',
        '1710' => 'Internal error',
        '1715' => 'Response timeout',
        '1025' => 'Insufficient credit',
        '1032' => 'Destination is on the do-not-disturb list',
        '1028' => 'Rejected as spam',
    ];

    /**
     * RouteMobile answers in plain text, one item per recipient separated
     * by commas: 1701|number|message-id for an accepted message, any other
     * leading code for a rejected one. A batch can mix the two, so it only
     * counts as a success when every item is 1701.
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $items = array_filter(array_map('trim', explode(',', (string) $body)));

        if($items === [] || !preg_match('/^\d{4}(\||$)/', (string) reset($items))) {
            return parent::interpretResponse($body, $httpCode);
        }

        $messageIds = [];

        foreach ($items as $item) {
            $fields = explode('|', $item);

            if($fields[0] !== '1701') {
                return [
                    'success'       => false,
                    'error_code'    => $fields[0],
                    'error_message' => self::REPLY_CODES[$fields[0]] ?? 'RouteMobile rejected the message with code '.$fields[0],
                ];
            }

            $messageIds[] = $fields[2] ?? null;
        }

        return ['success' => true, 'message_id' => implode(',', array_filter($messageIds)) ?: null];
    }
}
