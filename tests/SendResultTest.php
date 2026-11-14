<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\SendResult;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;
use Tekkenking\Swissecho\Tests\Fixtures\FileBackedGateway;

class SendResultTest extends TestCase
{
    private string $replyFile;

    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->replyFile = tempnam(sys_get_temp_dir(), 'swissecho-reply-');

        $app['config']->set('swissecho.live', false);
        $app['config']->set('swissecho.fake', 'log');
        $app['config']->set('app.env', 'local');
        $app['config']->set('swissecho.sender', 'MyApp');

        foreach (['sms', 'voice', 'whatsapp'] as $route) {
            $app['config']->set("swissecho.routes_options.$route.default_place", 'nga');
        }

        $app['config']->set('swissecho.routes_options.whatsapp.gateway_options.kudisms.template_code', 'test_template');
        $app['config']->set('swissecho.routes_options.slack.auth.webhook', 'https://hooks.slack.com/services/T/B/x');
        $app['config']->set('swissecho.routes_options.sms.gateway_options.filebacked', [
            'class'      => FileBackedGateway::class,
            'reply_file' => $this->replyFile,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->replyFile);

        parent::tearDown();
    }

    private function sms(string $gateway = 'termii'): ?SendResult
    {
        return (new Swissecho())->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('08012345678')->content('hello');
        })->gateway($gateway)->go();
    }

    public function test_go_returns_the_result(): void
    {
        $result = $this->sms();

        //go() used to return null whether the send worked or not.
        $this->assertInstanceOf(SendResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertFalse($result->failed());
        $this->assertTrue($result->mocked);
        $this->assertSame(['2348012345678'], $result->to);
        $this->assertSame('sms', strtolower($result->route));
        $this->assertSame('termii', $result->gateway);
        $this->assertNull($result->providerRes, 'nothing came back from a provider in mock mode');
    }

    public function test_the_result_reads_as_an_array_too(): void
    {
        $result = $this->sms();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('provider_res', $result->toArray());
        $this->assertSame(
            ['success', 'message_id', 'error_code', 'error_message', 'provider_res', 'to', 'route', 'gateway', 'mocked'],
            array_keys($result->toArray())
        );
    }

    public function test_quick_returns_the_result(): void
    {
        $this->assertInstanceOf(SendResult::class, (new Swissecho())->quick('08012345678', 'hello'));
    }

    public function test_a_notification_returns_one_result_per_route(): void
    {
        $notification = new class extends Notification {
            public function swissechoRoutes($notifiable): array
            {
                return ['sms', 'whatsapp'];
            }

            public function toSms($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('sms body');
            }

            public function toWhatsapp($notifiable): SwissechoMessage
            {
                return (new SwissechoMessage())->content('whatsapp body');
            }
        };

        $results = (new Swissecho())->send(new class { public string $phone = '08012345678'; }, $notification);

        $this->assertSame(['sms', 'whatsapp'], array_keys($results));
        $this->assertContainsOnlyInstancesOf(SendResult::class, $results);
    }

    public function test_the_event_carries_the_result_and_the_normalised_keys(): void
    {
        $captured = null;
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$captured) {
            $captured = $event;
        });

        $result = $this->sms();

        $this->assertSame($result, $captured->result);

        foreach (['success', 'message_id', 'error_code', 'error_message', 'provider_res', 'mocked'] as $key) {
            $this->assertArrayHasKey($key, $captured->formattedResponse);
        }

        //The keys existing listeners read are still there.
        $this->assertArrayHasKey('status', $captured->formattedResponse);
        $this->assertArrayHasKey('partner_response', $captured->formattedResponse);
    }

    public function test_a_send_to_several_recipients_folds_into_one_result(): void
    {
        $result = (new Swissecho())->route('voice', function (SwissechoMessage $ms) {
            return $ms->to('08012345678,08087654321')->content('123456');
        })->go();

        $this->assertTrue($result->success);
        $this->assertSame(['2348012345678', '2348087654321'], $result->to);
        $this->assertCount(2, $result->results);
        $this->assertSame('2348087654321', $result->results[1]->to);
    }

    public function test_slack_reports_the_same_shape(): void
    {
        $result = (new Swissecho())->route('slack', function (SwissechoMessage $ms) {
            return $ms->content('hello');
        })->go();

        $this->assertInstanceOf(SendResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertTrue($result->mocked);
    }

    public function test_a_rejection_inside_the_body_is_a_failure_with_the_raw_reply(): void
    {
        config(['swissecho.live' => true]);
        file_put_contents($this->replyFile, '{"ok":false,"code":"E42","reason":"Insufficient balance"}');

        $result = $this->sms('filebacked');

        //The transport worked and there is no HTTP error to go on. Only the
        //gateway's own reading of the body can tell this was rejected.
        $this->assertFalse($result->success);
        $this->assertSame('E42', $result->errorCode);
        $this->assertSame('Insufficient balance', $result->errorMessage);
        $this->assertSame('{"ok":false,"code":"E42","reason":"Insufficient balance"}', $result->providerRes);
        $this->assertFalse($result->mocked);
    }

    public function test_an_accepted_message_carries_the_providers_id(): void
    {
        config(['swissecho.live' => true]);
        file_put_contents($this->replyFile, '{"ok":true,"id":"msg-9001"}');

        $result = $this->sms('filebacked');

        $this->assertTrue($result->success);
        $this->assertSame('msg-9001', $result->messageId);
        $this->assertSame('{"ok":true,"id":"msg-9001"}', $result->providerRes);
    }

    public function test_combining_takes_the_first_failure(): void
    {
        $combined = SendResult::combine([
            new SendResult(success: true, messageId: 'a', to: '1'),
            new SendResult(success: false, errorCode: '7', errorMessage: 'barred', to: '2'),
        ]);

        $this->assertFalse($combined->success);
        $this->assertSame('7', $combined->errorCode);
        $this->assertSame('barred', $combined->errorMessage);
        $this->assertSame(['1', '2'], $combined->to);
    }

    public function test_a_result_cannot_be_changed(): void
    {
        $this->expectException(\LogicException::class);

        $result = new SendResult(success: true);
        $result['success'] = false;
    }
}
