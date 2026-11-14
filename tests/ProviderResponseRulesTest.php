<?php

namespace Tekkenking\Swissecho\Tests;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Montnets\Montnets;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Nigerianbulksms\Nigerianbulksms;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Routemobile\RouteMobile;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Smsbroadcast\SmsBroadCastDotComDotAu;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Termii\Termii;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Tnz\Tnz;
use Tekkenking\Swissecho\Routes\Sms\Gateways\Wirepick\Wirepick;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Termii\TermiiVoiceCall;
use Tekkenking\Swissecho\Routes\Voice\Gateways\Textngxyz\TextngxyzVoiceCall;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\DirectWhatsapp\DirectWhatsapp;
use Tekkenking\Swissecho\Routes\Whatsapp\Gateways\Kudisms\KudismsWhatsapp;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

/**
 * Each gateway reads its own provider's reply. The bodies here are the
 * examples from each provider's documentation, so a rule that drifts from
 * the documented format fails here rather than in production.
 */
class ProviderResponseRulesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    private function judge(string $gatewayClass, string $body, int $httpCode): array
    {
        $message = (new SwissechoMessage())->to(['2348012345678'])->sender('MyApp')->line('hello');
        $gateway = new $gatewayClass([], $message);

        return (new \ReflectionMethod($gateway, 'interpretResponse'))->invoke($gateway, $body, $httpCode);
    }

    public static function replies(): array
    {
        return [
            // gateway, body, http, success, message_id, error_code, error_message
            'termii accepted' => [Termii::class, '{"code":"ok","balance":1047.57,"message_id":"3017544054459083819856413","message":"Successfully Sent","message_id_str":"3017544054459083819856413"}', 200, true, '3017544054459083819856413', null, null],
            'termii rejected' => [Termii::class, '{"message":"Invalid API key"}', 401, false, null, '401', 'Invalid API key'],
            'termii voice accepted' => [TermiiVoiceCall::class, '{"code":"ok","message_id":"3017545719517588296261695","pinId":"3017545719517588296261695","message":"Successfully Sent"}', 200, true, '3017545719517588296261695', null, null],

            'nigeriabulksms accepted' => [Nigerianbulksms::class, '{"status":"OK","count":1,"price":8}', 200, true, null, null, null],
            'nigeriabulksms rejected inside a 200' => [Nigerianbulksms::class, '{"error":"Login denied.","errno":"103"}', 200, false, null, '103', 'Login denied.'],
            'nigeriabulksms unrecognised body falls back to http' => [Nigerianbulksms::class, 'gateway timeout', 504, false, null, '504', 'Gateway rejected the message with HTTP 504'],

            'routemobile accepted' => [RouteMobile::class, '1701|2348012345678|bc5f7425-c98c-445b-a1f7-4fc5e2acef7e', 200, true, 'bc5f7425-c98c-445b-a1f7-4fc5e2acef7e', null, null],
            'routemobile two accepted' => [RouteMobile::class, '1701|2348012345678|id-one,1701|2348087654321|id-two', 200, true, 'id-one,id-two', null, null],
            'routemobile bad login inside a 200' => [RouteMobile::class, '1703', 200, false, null, '1703', 'Invalid username or password'],
            'routemobile batch cut short by credit' => [RouteMobile::class, '1701|2348012345678|id-one,1025|2348087654321', 200, false, null, '1025', 'Insufficient credit'],

            'smsbroadcast accepted' => [SmsBroadCastDotComDotAu::class, 'OK: 61400111222:2942263', 200, true, '2942263', null, null],
            'smsbroadcast bad number inside a 200' => [SmsBroadCastDotComDotAu::class, 'BAD:0400abc111:Invalid Number', 200, false, null, null, 'Invalid Number'],
            'smsbroadcast bad login inside a 200' => [SmsBroadCastDotComDotAu::class, 'ERROR: Username or password is incorrect.', 200, false, null, null, 'Username or password is incorrect.'],

            'tnz accepted, older shape' => [Tnz::class, '{"Result":"Success","MessageID":"AAAAAAAA-BBBB"}', 200, true, 'AAAAAAAA-BBBB', null, null],
            'tnz accepted, v3 shape' => [Tnz::class, '{"MessageID":"1000000a-f002-4007-b00a-d00000000002"}', 200, true, '1000000a-f002-4007-b00a-d00000000002', null, null],
            'tnz rejected with a list' => [Tnz::class, '{"Result":"Failed","ErrorMessage":["Missing or empty field"]}', 400, false, null, '400', 'Missing or empty field'],
            'tnz rejected with a string' => [Tnz::class, '{"Result":"Unauthorized","ErrorMessage":"Access denied"}', 401, false, null, '401', 'Access denied'],

            //The msgid is 64-bit and must survive as a string.
            'montnets accepted' => [Montnets::class, '{"result":0,"msgid":7832079989982321833,"custid":"b3d0"}', 200, true, '7832079989982321833', null, null],
            'montnets rejected inside a 200' => [Montnets::class, '{"result":-100012,"msgid":0,"custid":""}', 200, false, null, '-100012', 'Montnets rejected the message with result -100012'],

            'kudisms accepted' => [KudismsWhatsapp::class, '{"status":"success","error_code":"000","data":"234XXXXXXXX|42a455f3-8d4d-a775-4311-13790d5820c0","msg":"Message sent Successfully"}', 200, true, '42a455f3-8d4d-a775-4311-13790d5820c0', null, null],
            'kudisms rejected inside a 200' => [KudismsWhatsapp::class, '{"status":"error","error_code":"109","msg":"Insufficient credit"}', 200, false, null, '109', 'Insufficient credit'],

            'textng accepted' => [TextngxyzVoiceCall::class, '{"status":"Successful","reference":"ref-123","route":"VOICE-OTP"}', 200, true, 'ref-123', null, null],
            'textng rejected inside a 200' => [TextngxyzVoiceCall::class, '{"status":"ERROR","comment":"Invalid key"}', 200, false, null, null, 'Invalid key'],

            'whatsapp cloud accepted' => [DirectWhatsapp::class, '{"messaging_product":"whatsapp","contacts":[{"input":"2348012345678","wa_id":"2348012345678"}],"messages":[{"id":"wamid.HBgL"}]}', 200, true, 'wamid.HBgL', null, null],
            'whatsapp cloud rejected' => [DirectWhatsapp::class, '{"error":{"message":"(#130429) Rate limit hit","type":"OAuthException","code":130429,"error_data":{"messaging_product":"whatsapp","details":"Cloud API message throughput has been reached."}}}', 400, false, null, '130429', '(#130429) Rate limit hit Cloud API message throughput has been reached.'],

            //No documented format, so HTTP status is all there is to go on.
            'wirepick has no rule: 200 passes' => [Wirepick::class, '<messages><sms><status>ACT</status></sms></messages>', 200, true, null, null, null],
            'wirepick has no rule: 500 fails' => [Wirepick::class, 'Server Error', 500, false, null, '500', 'Gateway rejected the message with HTTP 500'],
        ];
    }

    #[DataProvider('replies')]
    public function test_it_reads_the_providers_reply(
        string $gateway, string $body, int $http, bool $success, ?string $messageId, ?string $errorCode, ?string $errorMessage
    ): void {
        $verdict = $this->judge($gateway, $body, $http);

        $this->assertSame($success, $verdict['success']);
        $this->assertSame($messageId, isset($verdict['message_id']) ? (string) $verdict['message_id'] : null);
        $this->assertSame($errorCode, $verdict['error_code'] ?? null);
        $this->assertSame($errorMessage, $verdict['error_message'] ?? null);
    }
}
