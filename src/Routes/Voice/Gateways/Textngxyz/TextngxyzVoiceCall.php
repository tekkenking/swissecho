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
        $curl = curl_init($this->config['url']);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        return $curl;
    }

    /**
     * Textng answers {"status":"ERROR","comment":"..."} for a rejection and
     * a status of "Successful" with a `reference` otherwise. Used only when
     * a status field is present.
     * https://textngcommunications.com/api/v1/voice-otp/
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $data = $this->decodeJson($body);

        if($data === null || !is_string($data['status'] ?? null)) {
            return parent::interpretResponse($body, $httpCode);
        }

        if(strtoupper($data['status']) === 'ERROR') {
            return ['success' => false, 'error_message' => (string) ($data['comment'] ?? 'Textng rejected the call')];
        }

        return parent::interpretResponse($body, $httpCode) + ['message_id' => $data['reference'] ?? null];
    }
}
