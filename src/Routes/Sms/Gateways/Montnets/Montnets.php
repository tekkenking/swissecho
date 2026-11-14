<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Montnets;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

class Montnets extends BaseGateway
{

    /**
     * @inheritDoc
     */
    public function init(): string
    {
        $timestamp = date('mdHis');
        $username = $this->config['auth']['username'];
        $password = $this->config['auth']['password'];

        $data = [
            'userid' => $username,
            'pwd' => $this->encryptedPassword($username, $password, $timestamp),
            'content' => $this->body,
            'exno' => $this->sender,
            'mobile' => implode(',', $this->to),
            'timestamp' => $timestamp
        ];

        return json_encode($data);
    }

    public function send($data)
    {
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
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        return $curl;
    }

    /**
     * Encrypt password using specified rules.
     *
     * @param string $userid User ID
     * @param string $pwd Password
     * @param string $timestamp Timestamp
     * @return string Encrypted password
     */
    private function encryptedPassword($userid, $pwd, $timestamp): string
    {
        $uppercaseUserId = $userid !== null ? strtoupper($userid) : '';
        $fixedString = '00000000';
        $concatenatedString = $uppercaseUserId . $fixedString . $pwd . $timestamp;

        return md5($concatenatedString);
    }

    /**
     * Montnets reports its verdict in a `result` field: 0 accepted, with
     * the message in `msgid`, and any other value rejected. Used only when
     * that field is present; otherwise HTTP status decides.
     * https://www.int-montnets.com/en/a2pkaifa/77.html
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $data = $this->decodeJson($body);

        if($data === null || !array_key_exists('result', $data)) {
            return parent::interpretResponse($body, $httpCode);
        }

        if((string) $data['result'] !== '0') {
            return [
                'success'       => false,
                'error_code'    => (string) $data['result'],
                'error_message' => isset($data['desc']) ? (string) $data['desc'] : 'Montnets rejected the message with result '.$data['result'],
            ];
        }

        return ['success' => true, 'message_id' => isset($data['msgid']) ? (string) $data['msgid'] : null];
    }
}
