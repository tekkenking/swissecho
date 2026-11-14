<?php

namespace Tekkenking\Swissecho\Routes\Voice\Gateways\Termii;

use Tekkenking\Swissecho\Routes\Voice\Gateways\BaseGateway;

class TermiiVoiceCall extends BaseGateway
{

    /**
     * @return array
     */
    public function init(): array
    {
        $data = [
            "api_key"   => $this->config['auth']['api_key'],
            "phone_number"        => $this->to,
            "code"       => $this->body
        ];

        return $data;
    }

    public function send($data): \CurlHandle|bool
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        return $curl;
    }

    /**
     * Same convention as Termii SMS: HTTP status for failures, and a
     * message_id (also returned as pinId) on success.
     * https://developers.termii.com/voice-call
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $verdict = parent::interpretResponse($body, $httpCode);
        $data = $this->decodeJson($body);

        if($data === null) {
            return $verdict;
        }

        if(!$verdict['success']) {
            $verdict['error_message'] = is_string($data['message'] ?? null) ? $data['message'] : $verdict['error_message'];

            return $verdict;
        }

        return ['success' => true, 'message_id' => $data['message_id'] ?? $data['pinId'] ?? null];
    }
}
