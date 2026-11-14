<?php

namespace Tekkenking\Swissecho\Tests\Fixtures;

use Tekkenking\Swissecho\Routes\Sms\Gateways\BaseGateway;

/**
 * A gateway whose "provider" is a local file, so the live request path -
 * curl_exec, interpretResponse, the result and the event - can be exercised
 * with no network. The file's contents play the provider's reply.
 */
class FileBackedGateway extends BaseGateway
{
    public function init(): mixed
    {
        return ['to' => $this->to, 'text' => $this->body];
    }

    public function send($data)
    {
        $handle = curl_init('file://' . $this->config['reply_file']);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        return $handle;
    }

    /**
     * Like several real providers: every reply arrives the same way, and
     * the rejection is only visible inside the body.
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $data = json_decode((string) $body, true) ?: [];

        if (($data['ok'] ?? false) !== true) {
            return ['success' => false, 'error_code' => $data['code'] ?? null, 'error_message' => $data['reason'] ?? 'rejected'];
        }

        return ['success' => true, 'message_id' => $data['id'] ?? null];
    }
}
