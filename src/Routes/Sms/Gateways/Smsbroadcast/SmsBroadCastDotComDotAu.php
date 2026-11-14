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

        $source = $this->sender;
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

    /**
     * SMS Broadcast answers in plain text, one line per recipient:
     * "OK: 61400111222:2942263" accepted, "BAD:0400abc111:Invalid Number"
     * rejected, or a single "ERROR: ..." line for the whole request. All of
     * them arrive as HTTP 200.
     * https://smsbroadcast.com.au/wp-content/uploads/2023/01/Advanced-HTTP-API.pdf
     */
    protected function interpretResponse(mixed $body, int $httpCode): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $body) ?: [])));

        if($lines === [] || !preg_match('/^(OK|BAD|ERROR)\s*:/i', $lines[0])) {
            return parent::interpretResponse($body, $httpCode);
        }

        $messageIds = [];

        foreach ($lines as $line) {
            $fields = array_map('trim', explode(':', $line, 3));
            $kind = strtoupper($fields[0]);

            if($kind === 'ERROR') {
                return ['success' => false, 'error_message' => trim(substr($line, strpos($line, ':') + 1))];
            }

            if($kind === 'BAD') {
                return ['success' => false, 'error_message' => $fields[2] ?? 'SMS Broadcast rejected the number'];
            }

            $messageIds[] = $fields[2] ?? null;
        }

        return ['success' => true, 'message_id' => implode(',', array_filter($messageIds)) ?: null];
    }
}
