<?php

namespace Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;
use Tekkenking\Swissecho\SwissechoException;

/**
 * TNZ REST API v3.00.
 * https://www.tnz.co.nz/docs/restapi/
 */
class Tnz extends BaseGateway
{
    public const DEFAULT_URL = 'https://api.tnz.co.nz/api/v3.00/sms';

    /**
     * One request carries every recipient, as a Destinations list.
     *
     * @return array
     */
    public function init(): array
    {
        $data = [
            'Message'      => $this->body,
            'Destinations' => array_map(
                fn ($number) => ['ToNumber' => $this->international((string) $number)],
                (array) $this->to
            ),
        ];

        $from = $this->fromNumber();

        if($from !== null) {
            $data['FromNumber'] = $from;
        }

        return $data;
    }

    /**
     * @param array $data
     * @return \CurlHandle
     * @throws SwissechoException
     */
    public function send($data): \CurlHandle
    {
        $ch = curl_init($this->config['url'] ?? null ?: self::DEFAULT_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());

        return $ch;
    }

    /**
     * v3 authenticates with the account's JWT auth token as a Bearer token.
     * v2 took it as Basic, which v3 answers with 401.
     *
     * @return array<int, string>
     * @throws SwissechoException
     */
    protected function headers(): array
    {
        $token = $this->config['auth']['token'] ?? $this->config['auth']['api_key'] ?? null;

        if(empty($token)) {
            throw new SwissechoException('Sms: set TNZ_AUTH_TOKEN for the tnz gateway.');
        }

        return [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        ];
    }

    /**
     * TNZ reads a number without a plus as a local one, so a number that
     * already carries its country code is marked as international.
     *
     * @param string $number
     * @return string
     */
    protected function international(string $number): string
    {
        return ctype_digit($number) ? '+'.$number : $number;
    }

    /**
     * FromNumber is a phone number in E.164 without the plus, so a sender
     * that is a name is left out and TNZ applies the account's own.
     *
     * @return string|null
     */
    protected function fromNumber(): ?string
    {
        $digits = ltrim($this->sender, '+');

        return $digits !== '' && ctype_digit($digits) ? $digits : null;
    }

    /**
     * An accepted send is HTTP 200 with a MessageID. A rejection carries a
     * Result of Failed or Unauthorized and an ErrorMessage, which the
     * specification types as a list and shows as a string.
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $verdict = parent::interpretResponse($body, $httpCode);
        $data = $this->decodeJson($body);

        if($data === null) {
            return $verdict;
        }

        if(in_array($data['Result'] ?? null, ['Failed', 'Unauthorized'], true)) {
            $reason = $data['ErrorMessage'] ?? $data['Result'];

            return [
                'success'       => false,
                'error_code'    => $verdict['error_code'] ?? null,
                'error_message' => is_array($reason) ? implode('; ', $reason) : (string) $reason,
            ];
        }

        return $verdict + ['message_id' => $data['MessageID'] ?? null];
    }
}
