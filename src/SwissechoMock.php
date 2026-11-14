<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SwissechoMock
{

    private array $config;

    private function mockBuildInfo($gatewayClass, $msgBuilder)
    {
        //No environment check: mock mode is switched on by swissecho.live
        //being false, not by the app environment. Gating this on 'local'
        //meant that on staging the build was an empty string, mockSend()
        //skipped the whole branch, and nothing was logged or mailed while
        //the send was still reported as successful.
        $to = is_array($msgBuilder->to) ? implode(',', $msgBuilder->to) : (string) $msgBuilder->to;
        $build  = "From: ". $msgBuilder->from."\n";
            $build .= "To: ". $to."\n";
        $build .= "Message: ". $msgBuilder->message . "\n";
        $build .= "============================================\n";
        $build .= "BUILD INFO: (Not included in the actual BODY):\n";
        $build .= "============================================\n";
        $build .= "Country (for ".$msgBuilder->route." route): ". $msgBuilder->place ."\n";
        $build .= "PhoneCode (for ".$msgBuilder->route." route): ". $msgBuilder->phonecode ."\n";
        $build .= "Route: ". $msgBuilder->route ."\n";
        $build .= "Gateway: ". $msgBuilder->gateway ."\n";
        $build .= "Gateway Class: ". $gatewayClass ."\n";

        return $build;
    }

    public function mockSend($gatewayClass, $msgBuilder)
    {
        $this->config = config('swissecho');
        $buildMock = $this->mockBuildInfo($gatewayClass, $msgBuilder);
        $mockMethod = 'mockBy'.Str::studly($this->config['fake'] ?? 'log');

        //Without this, a typo such as SWISSECHO_FAKE=logs produced
        //'Call to undefined method ...::mockByLogs()', an Error rather than
        //an Exception, so nothing downstream could catch it.
        if(!method_exists($this, $mockMethod)) {
            throw new SwissechoException(
                'Swissecho: unknown mock driver "'.$this->config['fake'].'". Set SWISSECHO_FAKE to log or mail.'
            );
        }

        if($buildMock) {
            $this->$mockMethod($buildMock, $msgBuilder);
            return [
                'status'    =>  true,
                'response'  =>  [
                    'gateway'   =>  $msgBuilder->gateway,
                    'route'   =>  $msgBuilder->route,
                    'message'   =>  $buildMock
                ],
                'from'      =>  $msgBuilder->from,
                'to'        =>  $msgBuilder->to,
                'body'      =>  $msgBuilder->message
            ];
        }

        return [];
    }

    public function mockByMail($buildMock, $msgBuilder)
    {
        if(empty($this->config['fake_mail'])) {
            throw new SwissechoException(
                'Swissecho: SWISSECHO_FAKE=mail needs SWISSECHO_FAKE_MAIL set to an address you own.'
            );
        }

        $to = is_array($msgBuilder->to) ? implode(',', $msgBuilder->to) : (string) $msgBuilder->to;
        Mail::raw($buildMock, function($message) use ($to) {
            $message->to([
                $this->config['fake_mail']
            ])->subject('Mock: ['.$to.']');
        });
    }

    public function mockByLog($buildMock, $msgBuilder)
    {
        $to = is_array($msgBuilder->to) ? implode(',', $msgBuilder->to) : (string) $msgBuilder->to;
        $this->_prepareLogFile();
        Log::channel('swissecho_mock')
            ->info("Mock: [".$to."] \n".$buildMock. "\n");
    }

    /*
     * @return void
     */
    private function _prepareLogFile(): void
    {
        $hasLogFile = config('logging.channels.swissecho_mock', null);

        if(!$hasLogFile) {

            $existingConfig = config('logging.channels');
            $existingConfig['swissecho_mock'] =  [
                'driver' => 'single',
                'path' => storage_path('logs/swissecho_mock.log'),
                'level' => 'debug',
            ];

            config(['logging.channels' => $existingConfig]);
        }

        $file = config('logging.channels.swissecho_mock.path');
        if(!File::exists($file)) {
            File::put($file, '');
        }

    }
}
