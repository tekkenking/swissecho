<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\AfterSend;
use Tekkenking\Swissecho\Swissecho;
use Tekkenking\Swissecho\SwissechoException;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class VoiceAndBodyValidationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('swissecho.live', false);
        $app['config']->set('swissecho.fake', 'log');
        $app['config']->set('app.env', 'local');
        $app['config']->set('swissecho.routes_options.sms.default_place', 'nga');
        $app['config']->set('swissecho.routes_options.voice.default_place', 'nga');
        $app['config']->set('swissecho.routes_options.whatsapp.default_place', 'nga');
        $app['config']->set('swissecho.routes_options.whatsapp.gateway_options.kudisms.template_code', 'test_template');
    }

    private function captured(array &$into): void
    {
        Event::listen(AfterSend::class, function (AfterSend $event) use (&$into) {
            $into[] = $event->formattedResponse;
        });
    }

    public function test_a_voice_call_reaches_every_recipient(): void
    {
        $sent = [];
        $this->captured($sent);

        (new Swissecho())->route('voice', function (SwissechoMessage $ms) {
            return $ms->to('08012345678,08087654321,08099988877')->content('123456');
        })->go();

        //Voice used to take reset($to) and call only the first number, while
        //the mock log and the event still listed all three.
        $this->assertSame(
            ['2348012345678', '2348087654321', '2348099988877'],
            array_column($sent, 'to')
        );
        $this->assertCount(3, $sent);
    }

    public function test_a_voice_otp_keeps_its_leading_zeros(): void
    {
        $sent = [];
        $this->captured($sent);

        (new Swissecho())->route('voice', function (SwissechoMessage $ms) {
            return $ms->to('08012345678')->content('012345');
        })->go();

        $this->assertSame('012345', $sent[0]['body']);
    }

    public static function routes(): array
    {
        return [
            'sms'      => ['sms'],
            'voice'    => ['voice'],
            'whatsapp' => ['whatsapp'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function test_a_message_with_no_body_raises_an_exception(string $route): void
    {
        //Previously: 'Undefined array key "message"' then a TypeError on the
        //non-nullable string property - an Error, so catch(Exception) around
        //the send did not catch it. Voice was worse: it cast the missing key
        //to '' and reported a successful send with an empty OTP code.
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('a message body is required');

        (new Swissecho())->route($route, function (SwissechoMessage $ms) {
            return $ms->to('08012345678');
        })->go();
    }

    public function test_whitespace_only_is_not_a_body(): void
    {
        $this->expectException(SwissechoException::class);
        $this->expectExceptionMessage('a message body is required');

        (new Swissecho())->route('sms', function (SwissechoMessage $ms) {
            return $ms->to('08012345678')->content('   ');
        })->go();
    }
}
